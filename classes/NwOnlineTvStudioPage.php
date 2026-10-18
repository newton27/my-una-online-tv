<?php
/**
 * Native UNA Studio module page candidate.
 * No database writes, migrations, imports, or untrusted actions.
 */
defined('BX_DOL') or die('Access denied');
class NwOnlineTvStudioPage extends BxTemplStudioModule
{
    public function getPageCode()
    {
        require_once __DIR__ . '/NwOnlineTvDashboard.php';
        return NwOnlineTvDashboard::render([
            'channels' => 0,
            'needs_review' => 0,
            'reports_open' => 0,
            'last_sync' => 'Not connected',
        ]) . '<p>Development preview: database statistics and Studio routing require native validation.</p>';
    }
}
