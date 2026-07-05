<?php
/**
 * osTicket plugin bootstrap.
 *
 * The API itself is served by the standalone web entry
 * (api/nks-osticket.php) which boots osTicket independently, so there is no
 * runtime hook to register here — this class exists so osTicket recognizes and
 * lists the plugin.
 */

require_once INCLUDE_DIR . 'class.plugin.php';
require_once __DIR__ . '/config.php';

class NksOsTicketApiPlugin extends Plugin
{
    public $config_class = 'NksOsTicketApiConfig';

    public function bootstrap()
    {
        // No signals to connect; the JSON API is served out-of-band.
    }
}
