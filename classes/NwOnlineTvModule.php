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
    /**
     * Member-facing form service for a native UNA Pages service block.
     * Uses logged-in account -> active profile from UNA, never a supplied profile ID.
     */
    public function serviceGetMemberFeedSubmissionMarkup(): string
    {
        require_once __DIR__ . '/NwOnlineTvMemberFeedForm.php';
        $accountId = function_exists('getLoggedId') ? (int)getLoggedId() : 0;
        $profileId = $accountId > 0
            ? (int)BxDolProfileQuery::getInstance()->getCurrentProfileByAccount($accountId)
            : 0;
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['feed_action'] ?? '') === 'submit') {
            $validToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
                && BxDolForm::isCsrfTokenValid($_POST['csrf_token']);
            try {
                $db = new PDO('mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
                    BX_DATABASE_USER, BX_DATABASE_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $message = NwOnlineTvMemberFeedForm::handle($db, $profileId, $validToken, $_POST);
            } catch (Throwable $e) {
                error_log('nw_online_tv member feed service: ' . get_class($e));
                $message = 'Feed submission temporarily unavailable.';
            }
        }
        return NwOnlineTvMemberFeedForm::render($profileId, BxDolForm::getCsrfToken(),
            htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'), $message);
    }

}
