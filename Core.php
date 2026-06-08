<?php

/**
 * Adlaire Ecosystem - Core.php
 *
 * @version 0.6
 * @php     >= 8.3
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/Logger.php')) {
    require_once __DIR__ . '/Logger.php';
}

if (PHP_VERSION_ID < 80300) {
    http_response_code(500);
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

// ============================================================
// Request
// ============================================================

final class Request
{
    private static array $trustedProxies = [];
    private string|false|null $rawInput = null;
    private string $method;
    private string $uri;
    private array $headers;
    private array $query;
    private mixed $body = null;
    private bool $bodyParsed = false;
    private string $ip;
    private array $routeParams = [];
    private array $routeInfo = [];

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $this->parseUri();
        $this->headers = $this->parseHeaders();
        $this->query   = $_GET;
        $this->ip      = $this->parseIp();
    }

    public static function setTrustedProxies(array $proxies): void
    {
        foreach ($proxies as $proxy) {
            if (!is_string($proxy) || filter_var($proxy, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('Trusted proxy must be a valid IP address.');
            }
        }
        self::$trustedProxies = array_values($proxies);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function body(): mixed
    {
        $this->ensureBodyParsed();
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $this->ensureBodyParsed();
        if (is_array($this->body) && array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function file(?string $key = null): mixed
    {
        if ($key === null) {
            return $_FILES;
        }
        return $_FILES[$key] ?? null;
    }

    public function hasValidFile(string $key): bool
    {
        $file = $this->file($key);
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'] ?? '');
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('authorization');
        if (!is_string($authorization)) {
            return null;
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches) !== 1) {
            return null;
        }
        return trim($matches[1]);
    }

    public function param(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->routeParams;
        }
        return $this->routeParams[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function setRouteInfo(array $info): void
    {
        $this->routeInfo = $info;
    }

    public function routeInfo(): array
    {
        return $this->routeInfo;
    }

    private function parseUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        if ($path === false || $path === null || $path === '') {
            return '/';
        }
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            } elseif ($key === 'REDIRECT_HTTP_AUTHORIZATION') {
                $headers['authorization'] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): mixed
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw = $this->rawInput();
            if ($raw === false || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            if ($this->method === 'POST') {
                return $_POST;
            }
            $raw = $this->rawInput();
            parse_str($raw === false ? '' : $raw, $parsed);
            return $parsed;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            return $_POST;
        }

        return null;
    }

    private function rawInput(): string|false
    {
        if ($this->rawInput !== null) {
            return $this->rawInput;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) {
            $this->rawInput = false;
            return false;
        }

        $this->rawInput = $raw;
        return $this->rawInput;
    }

    private function parseIp(): string
    {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $remoteIsTrusted = $remote !== '' && in_array($remote, self::$trustedProxies, true);

        if ($remoteIsTrusted) {
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    private function ensureBodyParsed(): void
    {
        if ($this->bodyParsed) {
            return;
        }
        $this->body = $this->parseBody();
        $this->bodyParsed = true;
    }
}

// ============================================================
// Response
// ============================================================

final class Response
{
    private int $statusCode = 200;
    private array $headers  = [];

    public function status(int $code): static
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('HTTP status code must be between 100 and 599.');
        }
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->assertHeader($name, $value);
        $this->headers[$name] = $value;
        return $this;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function cache(string $control, ?string $etag = null): static
    {
        $this->header('Cache-Control', $control);
        if ($etag !== null) {
            $this->header('ETag', $etag);
        }
        return $this;
    }

    public function cors(
        string $origin = '*',
        string $methods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        string $headers = 'Content-Type, Authorization'
    ): static {
        return $this->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', $methods)
            ->header('Access-Control-Allow-Headers', $headers);
    }

    public function json(mixed $data, ?int $status = null): never
    {
        if ($status !== null) {
            $this->status($status);
        }

        $this->sendHeaders('Content-Type: application/json; charset=utf-8');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            http_response_code(500);
            echo json_encode(['error' => 'JSON encoding failed: ' . json_last_error_msg()]);
        } else {
            echo $encoded;
        }

        exit;
    }

    public function error(string $message, int $status = 400, array $details = []): never
    {
        $payload = ['error' => ['message' => $message, 'status' => $status]];
        if ($details !== []) {
            $payload['error']['details'] = $details;
        }
        $this->json($payload, $status);
    }

    public function success(mixed $data, int $status = 200): never
    {
        $this->json(['data' => $data], $status);
    }

    public function paginated(array $result): never
    {
        $this->json($result);
    }

    public function redirect(string $url, int $status = 302): never
    {
        if (!in_array($status, [301, 302, 307, 308], true)) {
            throw new InvalidArgumentException('Redirect status must be 301, 302, 307, or 308.');
        }
        if (str_contains($url, "\r") || str_contains($url, "\n")) {
            throw new InvalidArgumentException('Redirect URL must not contain newlines.');
        }
        $this->status($status);
        $this->sendHeaders('Location: ' . $url);
        exit;
    }

    private function sendHeaders(?string $contentHeader = null): void
    {
        http_response_code($this->statusCode);
        if ($contentHeader !== null) {
            [$name, $value] = array_pad(explode(':', $contentHeader, 2), 2, '');
            if ($name !== '' && $value !== '') {
                $this->headers[trim($name)] = trim($value);
            }
            header($contentHeader);
        }
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    private function assertHeader(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid response header name: {$name}");
        }
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException("Invalid response header value for: {$name}");
        }
    }
}

// ============================================================
// Validator
// ============================================================

final class Validator
{
    private array $errors = [];
    private array $messages = [];

    public function __construct(private ?Database $database = null)
    {
    }

    public function validate(array $data, array $rules, array $messages = []): bool
    {
        $this->errors = [];
        $this->messages = $messages;

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet)
                ? array_values(array_filter(array_map('trim', explode('|', $ruleSet)), static fn(string $rule): bool => $rule !== ''))
                : $ruleSet;
            $targets = $this->resolveTargets($data, (string)$field);

            foreach ($targets as $targetField => $value) {
                $nullable = in_array('nullable', $ruleList, true);
                if ($nullable && ($value === null || $value === '')) {
                    continue;
                }

                foreach ($ruleList as $rule) {
                    if ($rule === 'nullable') {
                        continue;
                    }
                    $this->applyRule((string)$targetField, $value, $rule, $data);
                    if (in_array('bail', $ruleList, true) && isset($this->errors[(string)$targetField])) {
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    private function applyRule(string $field, mixed $value, mixed $rule, array $data): void
    {
        if ($rule instanceof Closure) {
            $result = $rule($value, $field, $data);
            if ($result === false) {
                $this->addError($field, 'custom', "{$field} is invalid.");
            } elseif (is_string($result) && $result !== '') {
                $this->addError($field, 'custom', $result);
            }
            return;
        }

        if (!is_string($rule)) {
            throw new InvalidArgumentException('Validation rule must be a string or Closure.');
        }

        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($ruleName) {
            'bail'        => null,
            'required'    => $this->validateRequired($field, $value),
            'required_if' => $this->validateRequiredIf($field, $value, (string)$param, $data),
            'string'      => $this->validateType($field, $value, 'string'),
            'int'         => $this->validateType($field, $value, 'int'),
            'strict_int'  => $this->validateType($field, $value, 'strict_int'),
            'float'       => $this->validateType($field, $value, 'float'),
            'bool'        => $this->validateType($field, $value, 'bool'),
            'array'       => $this->validateType($field, $value, 'array'),
            'min'         => $this->validateMin($field, $value, $this->numericParam($ruleName, $param)),
            'max'         => $this->validateMax($field, $value, $this->numericParam($ruleName, $param)),
            'regex'       => $this->validateRegex($field, $value, $this->stringParam($ruleName, $param)),
            'email'       => $this->validateEmail($field, $value),
            'url'         => $this->validateUrl($field, $value),
            'uuid'        => $this->validateUuid($field, $value),
            'date'        => $this->validateDate($field, $value),
            'in'          => $this->validateIn($field, $value, $this->stringParam($ruleName, $param)),
            'unique'      => $this->validateUnique($field, $value, $this->stringParam($ruleName, $param)),
            default       => throw new InvalidArgumentException("Unknown validation rule: {$ruleName}"),
        };
    }

    private function numericParam(string $ruleName, ?string $param): float
    {
        if ($param === null || $param === '' || !is_numeric($param)) {
            throw new InvalidArgumentException("{$ruleName} requires a numeric parameter.");
        }
        return (float)$param;
    }

    private function stringParam(string $ruleName, ?string $param): string
    {
        if ($param === null || $param === '') {
            throw new InvalidArgumentException("{$ruleName} requires a parameter.");
        }
        return $param;
    }

    private function resolveTargets(array $data, string $field): array
    {
        if (!str_contains($field, '*')) {
            return [$field => $this->getValue($data, $field)];
        }

        $segments = explode('.', $field);
        $targets = ['' => $data];

        foreach ($segments as $segment) {
            $next = [];
            foreach ($targets as $path => $value) {
                if ($segment === '*') {
                    if (!is_array($value)) {
                        continue;
                    }
                    foreach ($value as $index => $item) {
                        $next[$path === '' ? (string)$index : "{$path}.{$index}"] = $item;
                    }
                    continue;
                }

                $nextPath = $path === '' ? $segment : "{$path}.{$segment}";
                $next[$nextPath] = is_array($value) && array_key_exists($segment, $value) ? $value[$segment] : null;
            }
            $targets = $next;
        }

        return $targets === [] ? [$field => null] : $targets;
    }

    private function getValue(array $data, string $field): mixed
    {
        $value = $data;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private function addError(string $field, string $rule, string $message): void
    {
        $this->errors[$field][] = $this->messageFor($field, $rule, $message);
    }

    private function messageFor(string $field, string $rule, string $default): string
    {
        foreach (["{$field}.{$rule}", $field] as $key) {
            if (isset($this->messages[$key])) {
                return $this->messages[$key];
            }
        }

        foreach ($this->messages as $key => $message) {
            $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote((string)$key, '/')) . '$/';
            if (preg_match($pattern, $field) === 1 || preg_match($pattern, "{$field}.{$rule}") === 1) {
                return (string)$message;
            }
        }

        return $default;
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $this->addError($field, 'required', "{$field} is required.");
        }
    }

    private function validateRequiredIf(string $field, mixed $value, string $param, array $data): void
    {
        [$otherField, $expected] = array_pad(explode(',', $param, 2), 2, null);
        if ($otherField === null || $otherField === '' || $expected === null) {
            throw new InvalidArgumentException('required_if requires "field,value".');
        }
        if ((string)$this->getValue($data, $otherField) === $expected) {
            $this->validateRequired($field, $value);
        }
    }

    private function validateType(string $field, mixed $value, string $type): void
    {
        if ($value === null) {
            return;
        }

        $valid = match ($type) {
            'string' => is_string($value),
            'int'    => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'strict_int' => is_int($value),
            'float'  => is_float($value) || is_int($value) || is_numeric($value),
            'bool'   => is_bool($value),
            'array'  => is_array($value),
            default  => true,
        };

        if (!$valid) {
            $this->addError($field, $type, "{$field} must be of type {$type}.");
        }
    }

    private function validateMin(string $field, mixed $value, float $min): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value) && count($value) < (int)$min) {
            $this->addError($field, 'min', "{$field} must have at least {$min} items.");
        } elseif (is_string($value) && !is_numeric($value) && $this->stringLength($value) < (int)$min) {
            $this->addError($field, 'min', "{$field} must be at least {$min} characters.");
        } elseif (is_numeric($value) && (float)$value < $min) {
            $this->addError($field, 'min', "{$field} must be at least {$min}.");
        }
    }

    private function validateMax(string $field, mixed $value, float $max): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value) && count($value) > (int)$max) {
            $this->addError($field, 'max', "{$field} must have at most {$max} items.");
        } elseif (is_string($value) && !is_numeric($value) && $this->stringLength($value) > (int)$max) {
            $this->addError($field, 'max', "{$field} must be at most {$max} characters.");
        } elseif (is_numeric($value) && (float)$value > $max) {
            $this->addError($field, 'max', "{$field} must be at most {$max}.");
        }
    }

    private function validateRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value === null) {
            return;
        }

        set_error_handler(static fn() => true);
        $result = preg_match($pattern, (string)$value);
        restore_error_handler();

        if ($result === false) {
            $this->addError($field, 'regex', "{$field} has an invalid regex pattern.");
            return;
        }

        if ($result === 0) {
            $this->addError($field, 'regex', "{$field} format is invalid.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "{$field} must be a valid email address.");
        }
    }

    private function validateUrl(string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->addError($field, 'url', "{$field} must be a valid URL.");
        }
    }

    private function validateUuid(string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            $this->addError($field, 'uuid', "{$field} must be a valid UUID.");
        }
    }

    private function validateDate(string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value) || strtotime($value) === false) {
            $this->addError($field, 'date', "{$field} must be a valid date.");
        }
    }

    private function validateIn(string $field, mixed $value, string $param): void
    {
        if ($value === null) {
            return;
        }

        $allowed = array_map('trim', explode(',', $param));
        if (!in_array((string)$value, $allowed, true)) {
            $this->addError($field, 'in', "{$field} must be one of: " . implode(', ', $allowed) . '.');
        }
    }

    private function validateUnique(string $field, mixed $value, string $param): void
    {
        if ($value === null) {
            return;
        }

        if ($this->database === null && (!class_exists('Database') || !method_exists('Database', 'default'))) {
            throw new RuntimeException('unique validation requires Database::default().');
        }

        [$table, $column] = array_pad(explode(',', $param, 2), 2, null);
        if ($table === null || $table === '' || $column === null || $column === '') {
            throw new InvalidArgumentException('unique requires "table,column".');
        }

        $database = $this->database ?? Database::default();
        $count = $database->table($table)->where($column, $value)->count();
        if ($count > 0) {
            $this->addError($field, 'unique', "{$field} must be unique.");
        }
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }
        return strlen($value);
    }
}

// ============================================================
// Router
// ============================================================

final class RouteDefinition
{
    public function __construct(
        private Router $router,
        private int $index
    ) {
    }

    public function name(string $name): static
    {
        $this->router->nameRoute($this->index, $name);
        return $this;
    }

    public function where(string|array $param, ?string $pattern = null): static
    {
        if (is_array($param)) {
            foreach ($param as $name => $regex) {
                $this->router->constrainRoute($this->index, (string)$name, (string)$regex);
            }
            return $this;
        }

        if ($pattern === null) {
            throw new InvalidArgumentException('Route constraint pattern is required.');
        }

        $this->router->constrainRoute($this->index, $param, $pattern);
        return $this;
    }
}

final class Router
{
    private array $routes  = [];
    private array $staticRoutes = [];
    private array $names = [];
    private string $prefix = '';

    public function get(string $path, callable $handler): RouteDefinition
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): RouteDefinition
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): RouteDefinition
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): RouteDefinition
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): RouteDefinition
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function group(string $prefix, callable $callback): void
    {
        $previous = $this->prefix;
        $this->prefix = $this->normalizePath($previous . '/' . trim($prefix, '/'));
        try {
            $callback($this);
        } finally {
            $this->prefix = $previous;
        }
    }

    public function resource(string $name, string $controller): void
    {
        $base = '/' . trim($name, '/');
        $param = 'id';

        $this->get($base, $this->controllerAction($controller, 'index'))->name("{$name}.index");
        $this->post($base, $this->controllerAction($controller, 'store'))->name("{$name}.store");
        $this->get("{$base}/{{$param}}", $this->controllerAction($controller, 'show'))->name("{$name}.show");
        $this->put("{$base}/{{$param}}", $this->controllerAction($controller, 'update'))->name("{$name}.update");
        $this->patch("{$base}/{{$param}}", $this->controllerAction($controller, 'update'))->name("{$name}.patch");
        $this->delete("{$base}/{{$param}}", $this->controllerAction($controller, 'destroy'))->name("{$name}.destroy");
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new InvalidArgumentException("Unknown route name: {$name}");
        }

        $url = $this->names[$name];
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', rawurlencode((string)$value), $url);
        }

        if (preg_match('/\{[^}]+}/', $url) === 1) {
            throw new InvalidArgumentException("Missing parameters for route: {$name}");
        }

        return $url;
    }

    public function nameRoute(int $index, string $name): void
    {
        if (isset($this->names[$name])) {
            throw new InvalidArgumentException("Duplicate route name: {$name}");
        }
        if (!isset($this->routes[$index])) {
            throw new InvalidArgumentException('Route does not exist.');
        }
        if ($this->routes[$index]['name'] !== null) {
            throw new InvalidArgumentException('Route already has a name.');
        }
        $this->routes[$index]['name'] = $name;
        $this->names[$name] = $this->routes[$index]['path'];
    }

    public function constrainRoute(int $index, string $param, string $pattern): void
    {
        if (!isset($this->routes[$index])) {
            throw new InvalidArgumentException('Route does not exist.');
        }
        $this->routes[$index]['where'][$param] = $pattern;
        $compiled = $this->compileRoute($this->routes[$index]['path'], $this->routes[$index]['where']);
        $this->routes[$index]['pattern'] = $compiled['pattern'];
        $this->routes[$index]['paramNames'] = $compiled['paramNames'];
    }

    public function dispatch(Request $request, Response $response): never
    {
        $method = $request->method();
        $uri = $this->normalizePath($request->uri());
        $matchedMethods = [];

        $staticKey = $method . '#' . $uri;
        if (isset($this->staticRoutes[$staticKey])) {
            $route = $this->routes[$this->staticRoutes[$staticKey]];
            $request->setRouteParams([]);
            $request->setRouteInfo($this->routeDebugInfo($route, []));
            ($route['handler'])($request, $response);
            exit;
        }

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $candidateMethod) {
            if ($candidateMethod !== $method && isset($this->staticRoutes[$candidateMethod . '#' . $uri])) {
                $matchedMethods[] = $candidateMethod;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['paramNames'] === []) {
                continue;
            }
            $match = $this->matchRoute($route, $uri);
            if ($match === null) {
                continue;
            }

            $matchedMethods[] = $route['method'];
            if ($route['method'] !== $method) {
                continue;
            }

            $request->setRouteParams($match);
            $request->setRouteInfo($this->routeDebugInfo($route, $match));
            ($route['handler'])($request, $response);
            exit;
        }

        if ($matchedMethods !== []) {
            $request->setRouteInfo([
                'matched' => false,
                'failure' => '405',
                'allowed_methods' => array_values(array_unique($matchedMethods)),
            ]);
            $response->header('Allow', implode(', ', array_unique($matchedMethods)))
                ->error('Method Not Allowed', 405);
        }

        $request->setRouteInfo([
            'matched' => false,
            'failure' => '404',
        ]);
        $response->error('Not Found', 404);
    }

    private function addRoute(string $method, string $path, callable $handler): RouteDefinition
    {
        $fullPath = $this->normalizePath($this->prefix . '/' . trim($path, '/'));
        $compiled = $this->compileRoute($fullPath, []);
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'where' => [],
            'pattern' => $compiled['pattern'],
            'paramNames' => $compiled['paramNames'],
            'name' => null,
        ];

        $index = array_key_last($this->routes);
        if ($compiled['paramNames'] === []) {
            $this->staticRoutes[$method . '#' . $fullPath] = $index;
        }
        return new RouteDefinition($this, $index);
    }

    private function controllerAction(string $controller, string $method): Closure
    {
        return static function (Request $request, Response $response) use ($controller, $method): void {
            if (!class_exists($controller)) {
                throw new RuntimeException("Controller not found: {$controller}");
            }

            $instance = new $controller();
            if (!method_exists($instance, $method) || !is_callable([$instance, $method])) {
                throw new RuntimeException("Controller action not found: {$controller}::{$method}");
            }

            $instance->{$method}($request, $response);
        };
    }

    private function matchRoute(array $route, string $uri): ?array
    {
        set_error_handler(static fn(): bool => true);
        $result = preg_match($route['pattern'], $uri, $matches);
        restore_error_handler();

        if ($result === false) {
            throw new RuntimeException("Invalid route pattern: {$route['path']}");
        }

        if ($result !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }
        return $params;
    }

    private function compileRoute(string $path, array $where): array
    {
        $pattern = '';
        $paramNames = [];
        $offset = 0;

        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)}/', $path, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $index => $match) {
            [$token, $position] = $match;
            $pattern .= preg_quote(substr($path, $offset, $position - $offset), '#');
            $name = $matches[1][$index][0];
            $paramNames[] = $name;
            $constraint = str_replace('#', '\#', $where[$name] ?? '[^/]+');
            $pattern .= '(?P<' . $name . '>' . $constraint . ')';
            $offset = $position + strlen($token);
        }

        $pattern .= preg_quote(substr($path, $offset), '#');
        return [
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
        ];
    }

    private function routeDebugInfo(array $route, array $params): array
    {
        return [
            'matched' => true,
            'path' => $route['path'],
            'method' => $route['method'],
            'name' => $route['name'],
            'params' => $params,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

// ============================================================
// Adlaire - facade
// ============================================================

final class Adlaire
{
    private static ?Router $router = null;
    private static ?Request $request = null;
    private static ?Response $response = null;
    private static ?Logger $logger = null;
    private static float $startedAt = 0.0;

    public static function init(array $config = []): void
    {
        self::$startedAt = microtime(true);
        Request::setTrustedProxies($config['trustedProxies'] ?? []);
        self::$router = new Router();
        self::$request = new Request();
        self::$response = new Response();

        if (class_exists('Logger')) {
            self::$logger = Logger::fromConfig($config['logger'] ?? []);
        }

        if (self::$logger !== null) {
            register_shutdown_function(static function (): void {
                if (self::$request !== null && self::$response !== null && self::$logger !== null) {
                    self::$logger->debugRequest(self::$request, self::$response, self::$startedAt);
                }
            });
        }

        set_exception_handler(static function (Throwable $exception): never {
            $development = getenv('APP_ENV') === 'development';
            if (self::$logger !== null) {
                self::$logger->error('Uncaught exception.', [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'trace' => $development ? $exception->getTraceAsString() : null,
                ]);
            }

            $payload = [
                'error' => [
                    'message' => $development ? $exception->getMessage() : 'Internal Server Error',
                    'status' => 500,
                ],
            ];
            if ($development) {
                $payload['error']['class'] = $exception::class;
                $payload['error']['trace'] = $exception->getTrace();
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit(1);
        });
    }

    public static function router(): Router
    {
        if (self::$router === null) {
            throw new RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        }
        return self::$router;
    }

    public static function request(): Request
    {
        if (self::$request === null) {
            throw new RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        }
        return self::$request;
    }

    public static function response(): Response
    {
        if (self::$response === null) {
            throw new RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        }
        return self::$response;
    }

    public static function validate(array $data, array $rules, array $messages = [], ?Database $database = null): Validator
    {
        $validator = new Validator($database);
        $validator->validate($data, $rules, $messages);
        return $validator;
    }

    public static function run(): never
    {
        if (self::$router === null || self::$request === null || self::$response === null) {
            throw new RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        }
        self::$router->dispatch(self::$request, self::$response);
    }
}

Adlaire::init();
