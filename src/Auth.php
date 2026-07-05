<?php
/**
 * API key authentication and per-action authorization.
 *
 * Reuses osTicket's own `ost_api_key` table (key + bound IP + granular
 * `can_*` permission columns) so keys are managed the same way as core API
 * keys, but the permission model is our own.
 */

declare(strict_types=1);

namespace NksOsticket;

final class Auth
{
    /** @var array<string,mixed> Raw api key row. */
    private array $row;

    private function __construct(array $row)
    {
        $this->row = $row;
    }

    /**
     * Resolve and validate the caller from the X-Api-Key header + client IP.
     * Terminates with 401 on failure.
     */
    public static function require(Http $request): self
    {
        $key = $request->header('X-Api-Key');
        if ($key === null || $key === '') {
            Http::fail(401, 'Missing X-Api-Key header');
        }

        // Basic shape guard before touching the DB.
        if (!preg_match('/^[A-Za-z0-9]{16,255}$/', $key)) {
            Http::fail(401, 'Malformed API key');
        }

        $ip = Http::clientIp();
        $table = defined('API_KEY_TABLE') ? API_KEY_TABLE : 'ost_api_key';

        $sql = sprintf(
            "SELECT * FROM %s WHERE apikey = '%s' AND isactive = 1 LIMIT 1",
            $table,
            \db_real_escape($key)
        );
        $res = \db_query($sql);
        $row = $res ? \db_fetch_array($res) : null;

        if (!$row) {
            Http::fail(401, 'Invalid or inactive API key');
        }

        // IP binding: osTicket keys are pinned to a source IP. Surface the seen
        // IP on mismatch so operators can configure the key without guessing.
        if (!empty($row['ipaddr']) && $row['ipaddr'] !== $ip) {
            Http::fail(401, 'API key not authorized for this source IP', ['client_ip' => $ip]);
        }

        return new self($row);
    }

    public function keyId(): int
    {
        return (int)($this->row['id'] ?? 0);
    }

    /** Whether the key has a given `can_*` permission column set. */
    public function can(string $permissionColumn): bool
    {
        return !empty($this->row[$permissionColumn]);
    }

    /**
     * Require one of the given permission columns (OR semantics).
     * Terminates with 403 if none are granted.
     */
    public function requireAny(string ...$permissionColumns): void
    {
        foreach ($permissionColumns as $col) {
            if ($this->can($col)) {
                return;
            }
        }
        Http::fail(403, 'API key lacks permission: ' . implode(' | ', $permissionColumns));
    }
}
