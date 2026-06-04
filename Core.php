<?php

/**
 * Adlaire Ecosystem - Core.php
 * 
 * @version 0.2
 * @php     >= 8.3
 */

declare(strict_types=1);

// PHP バージョンチェック
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
    private string $method;
    private string $uri;
    private array $headers;
    private array $query;
    private mixed $body;
    private string $ip;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $this->parseUri();
        $this->headers = $this->parseHeaders();
        $this->query   = $_GET;
        $this->body    = $this->parseBody();
        $this->ip      = $this->parseIp();
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
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (is_array($this->body)) {
            return $this->body[$key] ?? $default;
        }
        return $default;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    private function parseUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        return $path !== false && $path !== null ? $path : '/';
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): mixed
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        return null;
    }

    private function parseIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
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
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // #2修正: int $status = null → ?int $status = null
    public function json(mixed $data, ?int $status = null): never
    {
        if ($status !== null) {
            $this->statusCode = $status;
        }

        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            http_response_code(500);
            echo json_encode(['error' => 'JSON encoding failed: ' . json_last_error_msg()]);
        } else {
            echo $encoded;
        }

        exit;
    }
}

// ============================================================
// Validator
// ============================================================

final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value    = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                // #3修正: $data引数を削除
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(?string $field = null): string|null
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    // #3修正: $data引数を削除
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'string'   => $this->validateType($field, $value, 'string'),
            'int'      => $this->validateType($field, $value, 'int'),
            'float'    => $this->validateType($field, $value, 'float'),
            'bool'     => $this->validateType($field, $value, 'bool'),
            'array'    => $this->validateType($field, $value, 'array'),
            'min'      => $this->validateMin($field, $value, (float)$param),
            'max'      => $this->validateMax($field, $value, (float)$param),
            'regex'    => $this->validateRegex($field, $value, (string)$param),
            'email'    => $this->validateEmail($field, $value),
            default    => null,
        };
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "{$field} is required.");
        }
    }

    private function validateType(string $field, mixed $value, string $type): void
    {
        if ($value === null) return;

        $valid = match ($type) {
            'string' => is_string($value),
            'int'    => is_int($value) || (is_string($value) && ctype_digit($value)),
            'float'  => is_float($value) || is_int($value) || is_numeric($value),
            'bool'   => is_bool($value),
            'array'  => is_array($value),
            default  => true,
        };

        if (!$valid) {
            $this->addError($field, "{$field} must be of type {$type}.");
        }
    }

    // #4修正: 文字列・数値・配列を排他的に判定
    private function validateMin(string $field, mixed $value, float $min): void
    {
        if ($value === null) return;

        if (is_array($value)) {
            if (count($value) < (int)$min) {
                $this->addError($field, "{$field} must have at least {$min} items.");
            }
        } elseif (is_string($value) && !is_numeric($value)) {
            if (mb_strlen($value) < (int)$min) {
                $this->addError($field, "{$field} must be at least {$min} characters.");
            }
        } elseif (is_numeric($value)) {
            if ((float)$value < $min) {
                $this->addError($field, "{$field} must be at least {$min}.");
            }
        }
    }

    // #4修正: 文字列・数値・配列を排他的に判定
    private function validateMax(string $field, mixed $value, float $max): void
    {
        if ($value === null) return;

        if (is_array($value)) {
            if (count($value) > (int)$max) {
                $this->addError($field, "{$field} must have at most {$max} items.");
            }
        } elseif (is_string($value) && !is_numeric($value)) {
            if (mb_strlen($value) > (int)$max) {
                $this->addError($field, "{$field} must be at most {$max} characters.");
            }
        } elseif (is_numeric($value)) {
            if ((float)$value > $max) {
                $this->addError($field, "{$field} must be at most {$max}.");
            }
        }
    }

    // #5修正: preg_match の無効パターンに対するエラーハンドリング追加
    private function validateRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value === null) return;

        set_error_handler(static fn() => true);
        $result = preg_match($pattern, (string)$value);
        restore_error_handler();

        if ($result === false) {
            $this->addError($field, "{$field} has an invalid regex pattern.");
            return;
        }

        if ($result === 0) {
            $this->addError($field, "{$field} format is invalid.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value === null) return;

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$field} must be a valid email address.");
        }
    }
}

// ============================================================
// Router
// ============================================================

final class Router
{
    private array $routes  = [];
    private string $prefix = '';

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function group(string $prefix, callable $callback): void
    {
        $previous     = $this->prefix;
        $this->prefix = $previous . '/' . trim($prefix, '/');
        $callback($this);
        $this->prefix = $previous;
    }

    public function dispatch(Request $request, Response $response): never
    {
        $method = $request->method();
        $uri    = '/' . trim($request->uri(), '/');

        $matchedPaths = [];

        foreach ($this->routes as $route) {
            if ($route['path'] === $uri) {
                $matchedPaths[] = $route['method'];
                if ($route['method'] === $method) {
                    ($route['handler'])($request, $response);
                    exit;
                }
            }
        }

        if (!empty($matchedPaths)) {
            $response->header('Allow', implode(', ', $matchedPaths))
                     ->json(['error' => 'Method Not Allowed'], 405);
        }

        $response->json(['error' => 'Not Found'], 404);
    }

    // #1修正: 無意味な自己代入を削除
    private function addRoute(string $method, string $path, callable $handler): void
    {
        $combined = $this->prefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($combined, '/');

        $this->routes[] = [
            'method'  => $method,
            'path'    => $fullPath,
            'handler' => $handler,
        ];
    }
}

// ============================================================
// Adlaire - ファサード
// ============================================================

final class Adlaire
{
    private static ?Router   $router   = null;
    private static ?Request  $request  = null;
    private static ?Response $response = null;

    // #6修正: 未初期化アクセス防止のため各メソッドでinit保証
    public static function init(): void
    {
        self::$router   = new Router();
        self::$request  = new Request();
        self::$response = new Response();
    }

    public static function router(): Router
    {
        self::$router ?? throw new \RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        return self::$router;
    }

    public static function request(): Request
    {
        self::$request ?? throw new \RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        return self::$request;
    }

    public static function response(): Response
    {
        self::$response ?? throw new \RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        return self::$response;
    }

    public static function validate(array $data, array $rules): Validator
    {
        $v = new Validator();
        $v->validate($data, $rules);
        return $v;
    }

    public static function run(): never
    {
        self::$router ?? throw new \RuntimeException('Adlaire not initialized. Call Adlaire::init() first.');
        self::$router->dispatch(self::$request, self::$response);
    }
}

// ============================================================
// Bootstrap
// ============================================================

Adlaire::init();
