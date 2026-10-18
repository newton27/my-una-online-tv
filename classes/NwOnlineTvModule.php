<?php
/**
 * My UNA World Online TV — native UNA module entrypoint candidate.
 * No privileged write actions are exposed.
 */
defined('BX_DOL') or die('Access denied');
class NwOnlineTvModule extends BxDolModule
{
    public function serviceGetDashboardMarkup(array $metrics = []): string
    {
        require_once __DIR__ . '/NwOnlineTvDashboard.php';
        return NwOnlineTvDashboard::render($metrics);
    }
    public function serviceGetDirectoryMarkup(array $pageData = []): string
    {
        require_once __DIR__ . '/NwOnlineTvDirectory.php';
        return NwOnlineTvDirectory::render($pageData);
    }
}
