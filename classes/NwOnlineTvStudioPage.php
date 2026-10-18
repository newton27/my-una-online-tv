<?php
/**
 * Read-only UNA 15 Studio overview. No catalog mutations or legacy module access.
 */
defined('BX_DOL') or die('Access denied');
class NwOnlineTvStudioPage extends BxTemplStudioModule
{
    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);
        $this->sPageDefault = 'overview';
        if ($sPage === '') $this->sPage = 'overview';
        $this->aMenuItems = [
            'overview' => ['name' => 'overview', 'icon' => 'tachometer', 'title' => 'Overview'],
            'channels' => ['name' => 'channels', 'icon' => 'tv', 'title' => 'Channels'],
            'sync' => ['name' => 'sync', 'icon' => 'refresh', 'title' => 'Sync'],
            'reports' => ['name' => 'reports', 'icon' => 'flag', 'title' => 'Reports'],
            'settings' => ['name' => 'settings', 'icon' => 'cog', 'title' => 'Settings'],
        ];
    }

    public function getPageCode($sPage = '', $bWrap = true)
    {
        require_once __DIR__ . '/NwOnlineTvDashboard.php';
        $metrics = ['channels' => 0, 'needs_review' => 0, 'reports_open' => 0, 'last_sync' => 'Never'];
        $error = '';
        try {
            $db = new mysqli(BX_DATABASE_HOST, BX_DATABASE_USER, BX_DATABASE_PASS, BX_DATABASE_NAME);
            if ($db->connect_errno) {
                throw new RuntimeException('Database unavailable');
            }
            $queries = [
                'channels' => 'SELECT COUNT(*) FROM nw_online_tv_channels',
                'needs_review' => 'SELECT COUNT(*) FROM nw_online_tv_channels WHERE review_required = 1',
                'reports_open' => 'SELECT COUNT(*) FROM nw_online_tv_reports WHERE resolved_at IS NULL',
            ];
            foreach ($queries as $key => $sql) {
                $result = $db->query($sql);
                if ($result === false) throw new RuntimeException('Metrics query failed');
                $metrics[$key] = (int)$result->fetch_row()[0];
            }
            $result = $db->query("SELECT MAX(finished_at) FROM nw_online_tv_sync_runs WHERE result = 'completed' AND finished_at IS NOT NULL");
            if ($result === false) throw new RuntimeException('Sync query failed');
            $timestamp = (int)$result->fetch_row()[0];
            if ($timestamp > 0) $metrics['last_sync'] = gmdate('Y-m-d H:i', $timestamp) . ' UTC';
            $db->close();
        } catch (Throwable $e) {
            error_log('nw_online_tv Studio metrics: ' . $e->getMessage());
            $error = '<p class="nwotv-alert" role="alert">Dashboard statistics are temporarily unavailable. Check the PHP error log.</p>';
        }
        $cssPath = dirname(__DIR__) . '/template/css/dashboard.css';
        $css = is_readable($cssPath) ? '<style>' . file_get_contents($cssPath) . '</style>' : '';
        $page = (string)($sPage !== '' ? $sPage : ($_GET['page'] ?? 'overview'));
        if (!in_array($page, ['overview', 'channels', 'sync', 'reports', 'settings'], true)) $page = 'overview';
        if ($page === 'channels') {
            try {
                require_once __DIR__ . '/NwOnlineTvChannelsStudio.php';
                $pdo = new PDO(
                    'mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
                    BX_DATABASE_USER,
                    BX_DATABASE_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                return $css . NwOnlineTvChannelsStudio::render($pdo);
            } catch (Throwable $e) {
                error_log('nw_online_tv Studio channels: ' . $e->getMessage());
                return $css . '<section class="nwotv-dashboard"><p class="nwotv-alert" role="alert">Channel list is temporarily unavailable. Check PHP error log.</p></section>';
            }
        }
        if ($page === 'overview') {
            return $css . $error . NwOnlineTvDashboard::render($metrics);
        }
        $titles = ['channels' => 'Channels', 'sync' => 'Channel synchronization', 'reports' => 'Channel reports', 'settings' => 'Settings'];
        $name = htmlspecialchars($titles[$page], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $css . '<section class="nwotv-dashboard"><header class="nwotv-heading"><h2>' . $name . '</h2><p>Management controls are under development. No changes can be made on this page yet.</p></header></section>';

    }
}
