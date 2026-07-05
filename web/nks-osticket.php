<?php
/**
 * NKS osTicket API — public entry point.
 *
 * Deploy this single file into osTicket's web-accessible `api/` directory.
 * A matching bypass rule in `api/.htaccess` keeps osTicket's default API
 * dispatcher (http.php) from swallowing the request:
 *
 *     RewriteRule ^nks-osticket\.php.*$ - [L]
 *
 * All routing, authentication and request handling live in the plugin kernel
 * under include/plugins/nks-osticket-api/.
 */

declare(strict_types=1);

// osTicket bootstrap must be loaded relative to THIS file's location.
require_once __DIR__ . '/../main.inc.php';

if (!defined('INCLUDE_DIR')) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo '{"ok":false,"error":{"code":500,"message":"osTicket bootstrap unavailable"}}';
    exit;
}

require_once INCLUDE_DIR . 'plugins/nks-osticket-api/src/Kernel.php';

NksOsticket\Kernel::run();
