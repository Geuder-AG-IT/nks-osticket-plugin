<?php
/**
 * Plugin configuration screen.
 *
 * API keys and their permissions are managed through osTicket's native API
 * keys (Admin Panel → Manage → API Keys), so this screen only surfaces
 * informational guidance rather than duplicating key storage.
 */

require_once INCLUDE_DIR . 'class.plugin.php';

class NksOsTicketApiConfig extends PluginConfig
{
    public function getOptions()
    {
        return array(
            'info' => new SectionBreakField(array(
                'label' => 'NKS osTicket API',
                'hint'  => 'A JSON API is exposed at /api/nks-osticket.php. '
                    . 'Authentication uses osTicket API keys (Admin → Manage → API Keys) '
                    . 'with per-key permission columns. No configuration is required here.',
            )),
        );
    }
}
