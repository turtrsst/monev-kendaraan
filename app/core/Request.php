<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $post;
    private array $files;
    private array $server;
    private ?array $jsonCache = null;
    private bool $jsonParsed = false;

    public function __construct(?array $query = null, ?array $post = null, ?array $files = null, ?array $server = null)
    {
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;
    }

    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        $m = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Override method untuk form PATCH/DELETE (opsional, tetap divalidasi CSRF)
        if ($m === 'POST') {
            $override = strtoupper((string)($this->post['_method'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $m;
    }

    public function path(): string
    {
        $uri = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . ltrim($path, '/');
        return $path === '//' ? '/' : (rtrim($path, '/') ?: '/');
    }

    public function fullUrl(): string
    {
        return (string)($this->server['REQUEST_URI'] ?? '/');
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path(), '/api/');
    }

    public function expectsJson(): bool
    {
        if ($this->isApi()) {
            return true;
        }
        $accept = (string)($this->server['HTTP_ACCEPT'] ?? '');
        $xhr = strtoupper((string)($this->server['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'XMLHTTPREQUEST';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string)$this->server[$key] : null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k);
        }
        return $out;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allPost(): array
    {
        $json = $this->json();
        return is_array($json) ? $json + $this->post : $this->post;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function json(): ?array
    {
        if ($this->jsonParsed) {
            return $this->jsonCache;
        }
        $this->jsonParsed = true;
        $ct = strtolower((string)($this->server['CONTENT_TYPE'] ?? ''));
        if (!str_contains($ct, 'application/json')) {
            $this->jsonCache = null;
            return null;
        }
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            $this->jsonCache = null;
            return null;
        }
        $decoded = json_decode($raw, true);
        $this->jsonCache = is_array($decoded) ? $decoded : null;
        return $this->jsonCache;
    }

    public function ip(): string
    {
        // Hanya percaya REMOTE_ADDR — jangan percaya X-Forwarded-For tanpa proxy tepercaya
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        $ua = (string)($this->server['HTTP_USER_AGENT'] ?? '');
        return mb_substr($ua, 0, 255);
    }

    public function isStateChanging(): bool
    {
        return in_array($this->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
