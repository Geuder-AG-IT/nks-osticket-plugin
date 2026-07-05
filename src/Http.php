<?php
/**
 * HTTP request/response helpers for the NKS osTicket API.
 *
 * Small, dependency-free wrapper around the PHP superglobals so the rest of
 * the kernel deals with a clean request object and a single JSON emitter.
 */

declare(strict_types=1);

namespace NksOsticket;

final class Http
{
    /** @var array<string,mixed> Merged input (JSON body over query string). */
    private array $input;

    private string $method;

    private function __construct(string $method, array $input)
    {
        $this->method = $method;
        $this->input = $input;
    }

    /**
     * Build the request from the current PHP environment.
     *
     * Accepts the action + params either as a JSON body (POST) or as query
     * parameters (GET), so read-only calls stay trivially curl-able.
     */
    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $input = [];

        // Query string first (lowest precedence).
        if (!empty($_GET)) {
            $input = $_GET;
        }

        // JSON body wins over query params.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    self::fail(400, 'Invalid JSON body');
                }
                $input = array_merge($input, $decoded);
            }
        }

        return new self($method, $input);
    }

    public function method(): string
    {
        return $this->method;
    }

    /** The requested action, e.g. "tickets.list". */
    public function action(): string
    {
        $action = $this->input['action'] ?? '';
        return is_string($action) ? trim($action) : '';
    }

    /**
     * Parameters for the action. Supports both a nested `params` object and
     * flat top-level keys so callers can use whichever is convenient.
     *
     * @return array<string,mixed>
     */
    public function params(): array
    {
        $params = [];
        if (isset($this->input['params']) && is_array($this->input['params'])) {
            $params = $this->input['params'];
        }
        // Flat keys (excluding envelope fields) also count as params.
        foreach ($this->input as $key => $value) {
            if ($key === 'action' || $key === 'params') {
                continue;
            }
            if (!array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /** Direct client IP as seen by the web server. */
    public static function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Emit a success envelope and terminate.
     *
     * @param mixed $data
     */
    public static function ok($data): void
    {
        self::emit(200, ['ok' => true, 'data' => $data]);
    }

    /** Emit an error envelope and terminate. */
    public static function fail(int $status, string $message, ?array $extra = null): void
    {
        $error = ['code' => $status, 'message' => $message];
        if ($extra !== null) {
            $error = array_merge($error, $extra);
        }
        self::emit($status, ['ok' => false, 'error' => $error]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function emit(int $status, array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, $status);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
