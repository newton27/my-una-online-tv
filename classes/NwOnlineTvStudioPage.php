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
            'overview' => ['name' => 'overview', 'icon' => 'home', 'title' => 'Overview'],
            'channels' => ['name' => 'channels', 'icon' => 'tv', 'title' => 'Channels'],
            'sync' => ['name' => 'sync', 'icon' => 'database', 'title' => 'Sync'],
            'reports' => ['name' => 'reports', 'icon' => 'flag', 'title' => 'Reports'],
            'settings' => ['name' => 'settings', 'icon' => 'cogs', 'title' => 'Settings'],
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
                $notice = '';
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                    try {
                        require_once __DIR__ . '/NwOnlineTvBulkPolicy.php';
                        $authorized = function_exists('isAdmin') && isAdmin();
                        $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
                            && BxDolForm::isCsrfTokenValid($_POST['csrf_token']);
                        $action = isset($_POST['bulk_action']) && is_string($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
                        $ids = isset($_POST['channel_ids']) && is_array($_POST['channel_ids']) ? $_POST['channel_ids'] : [];
                        if ($action === 'preview_delete') {
                            $preview = NwOnlineTvBulkPolicy::previewDeletion($pdo, $ids, $authorized, $csrf);
                            $notice = '<section class="nwotv-preview" role="status"><h3>Deletion preview — no changes made</h3><p><strong>' . (int)$preview['count'] . '</strong> selected channels from this page would be affected.</p><ol>';
                            foreach ($preview['titles'] as $title) {
                                $notice .= '<li>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                            }
                            $notice .= '</ol><p>Confirm only these explicit channel IDs. This permanently removes their associated reports too.</p>';
                            $notice .= '<form method="post" action="module.php?name=nw_online_tv&amp;page=channels">';
                            $notice .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)BxDolForm::getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
                            foreach ($preview['ids'] as $id) $notice .= '<input type="hidden" name="channel_ids[]" value="' . (int)$id . '">';
                            $notice .= '<p class="nwotv-confirm-label">You are about to permanently delete <strong>' . (int)$preview['count'] . '</strong> selected channel(s). Verify the names above, then confirm once. No typing required.</p>';
                            $notice .= '<div class="nwotv-confirm-actions"><button class="nwotv-confirm-danger" type="submit" name="bulk_action" value="delete_confirm">Permanently delete ' . (int)$preview['count'] . ' selected channel(s)</button><a class="nwotv-confirm-cancel" href="module.php?name=nw_online_tv&amp;page=channels">Cancel</a></div></form></section>';
                        } elseif ($action === 'delete_confirm') {
                            // The second, CSRF-protected confirmation click is the deliberate authorization.
                            // Retain the policy's exact-count check while removing repetitive manual typing.
                            $confirmedIds = NwOnlineTvBulkPolicy::channelIds($ids);
                            $affected = NwOnlineTvBulkPolicy::deleteSelected($pdo, $confirmedIds, $authorized, $csrf, 'DELETE ' . count($confirmedIds));
                            $notice = '<p class="nwotv-success" role="status">' . (int)$affected . ' channels permanently deleted.</p>';
                        } else {
                            $affected = NwOnlineTvBulkPolicy::applyReview($pdo, $action, $ids, $authorized, $csrf);
                            $notice = '<p class="nwotv-success" role="status">' . (int)$affected . ' channel records updated.</p>';
                        }
                    } catch (Throwable $e) {
                        // Show only a safe failure category. Never expose tokens, queries or exception text.
                        $category = !$authorized ? 'ADMIN' : (!$csrf ? 'CSRF' : ($e instanceof PDOException ? 'DATABASE' : 'VALIDATION'));
                        if ($category === 'VALIDATION') {
                            $message = $e->getMessage();
                            if ($message === 'Deletion confirmation did not match the selection count.') $category = 'CONFIRMATION_MISMATCH';
                            elseif (strpos($message, 'Selection changed') === 0) $category = 'SELECTION_CHANGED';
                            elseif (strpos($message, 'Select between 1 and 100') === 0 || strpos($message, 'Invalid channel identifier') === 0 || strpos($message, 'Duplicate channel identifiers') === 0) $category = 'INVALID_SELECTION';
                            elseif ($message === 'Unsupported deletion database driver.') $category = 'UNSUPPORTED_DRIVER';
                            elseif ($message === 'Deletion count mismatch. Transaction rolled back.') $category = 'COUNT_MISMATCH';
                        }
                        error_log('nw_online_tv bulk review [' . $category . ']: ' . get_class($e));
                        $notice = '<p class="nwotv-alert" role="alert">Action rejected (' . $category . '). No changes confirmed. Review the selection or contact the administrator.</p>';
                    }
                }
                return $css . $notice . NwOnlineTvChannelsStudio::render($pdo);
            } catch (Throwable $e) {
                error_log('nw_online_tv Studio channels: ' . $e->getMessage());
                return $css . '<section class="nwotv-dashboard"><p class="nwotv-alert" role="alert">Channel list is temporarily unavailable. Check PHP error log.</p></section>';
            }
        }
        if ($page === 'sync') {
            try {
                require_once __DIR__ . '/NwOnlineTvSyncStudio.php';
                $pdo = new PDO(
                    'mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
                    BX_DATABASE_USER, BX_DATABASE_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $notice='';
                if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
                    try {
                        require_once __DIR__ . '/NwOnlineTvSourcePolicy.php';
                        $auth=function_exists('isAdmin') && isAdmin();
                        $csrf=isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) && BxDolForm::isCsrfTokenValid($_POST['csrf_token']);
                        if (!$auth || !$csrf)throw new RuntimeException('Unauthorized source action.');
                        $action=(string)($_POST['source_action']??'');
                        $id=filter_var($_POST['source_id']??0,FILTER_VALIDATE_INT);
                        if($id===false || $id<0)throw new InvalidArgumentException('Invalid source ID.');
                        if($action==='save')NwOnlineTvSourcePolicy::save($pdo,$id,(string)($_POST['source_title']??''),(string)($_POST['source_url']??''),$auth);
                        else NwOnlineTvSourcePolicy::moderate($pdo,$id,$action,$auth);
                        $notice='<p class="nwotv-success" role="status">IPTV source updated. No import started.</p>';
                    } catch(Throwable $e){
                        error_log('nw_online_tv source action: '.get_class($e));
                        $notice='<p class="nwotv-alert" role="alert">Source change rejected. Check the source fields and your administrator session.</p>';
                    }
                }
                return $css . $notice . NwOnlineTvSyncStudio::render($pdo);
            } catch (Throwable $e) {
                error_log('nw_online_tv Studio sync: ' . $e->getMessage());
                return $css . '<section class="nwotv-dashboard"><p class="nwotv-alert" role="alert">Sync history is temporarily unavailable. Check PHP error log.</p></section>';
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
