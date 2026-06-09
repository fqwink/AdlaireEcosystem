<?php

/**
 * Adlaire Ecosystem - Middleware.php
 *
 * @version v0.201
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class MiddlewarePipeline
{
    private array $middleware = [];

    public function pipe(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function through(array $middleware): self
    {
        foreach ($middleware as $pipe) {
            if (!is_callable($pipe)) {
                throw new InvalidArgumentException('Middleware pipe must be callable.');
            }
            $this->pipe($pipe);
        }
        return $this;
    }

    public function pipes(): array
    {
        return $this->middleware;
    }

    public function process(mixed $passable, callable $destination): mixed
    {
        $next = $destination;
        foreach (array_reverse($this->middleware) as $middleware) {
            $next = static fn(mixed $passable): mixed => $middleware($passable, $next);
        }
        return $next($passable);
    }
}
