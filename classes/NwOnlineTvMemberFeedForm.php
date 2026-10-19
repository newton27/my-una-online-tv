<?php
/**
 * Member feed submission component. Render only inside an authenticated UNA
 * page/controller; caller supplies trusted profile id and CSRF validation.
 * The component never fetches or publishes feeds.
 */
defined('BX_DOL') or die('Access denied');
require_once __DIR__ . '/NwOnlineTvSourcePolicy.php';
final class NwOnlineTvMemberFeedForm
{
    private static function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function handle(PDO $db, int $authenticatedProfileId, bool $csrfValid, array $post): string
    {
        if ($authenticatedProfileId < 1) return 'Please sign in to submit an IPTV feed.';
        if (!$csrfValid) return 'Your session has expired. Refresh and try again.';
        $title = isset($post['feed_title']) && is_string($post['feed_title']) ? $post['feed_title'] : '';
        $url = isset($post['feed_url']) && is_string($post['feed_url']) ? $post['feed_url'] : '';
        try {
            // Bound repeated-submission abuse without contacting the feed URL.
            $count = $db->prepare("SELECT COUNT(*) FROM nw_online_tv_sources WHERE origin='member' AND submitted_profile_id=? AND created_at>?");
            $count->execute([$authenticatedProfileId,time()-86400]);
            if ((int)$count->fetchColumn() >= 3) return 'Daily submission limit reached. Try again tomorrow.';
            NwOnlineTvSourcePolicy::submit($db,$authenticatedProfileId,$title,$url);
            return 'Feed submitted for webmaster review. It remains disabled until approved.';
        } catch (Throwable $e) {
            error_log('nw_online_tv member submission: '.get_class($e));
            return 'Feed could not be submitted. Please check the title and HTTPS URL.';
        }
    }

    public static function render(int $authenticatedProfileId, string $csrfToken, string $actionUrl, string $message=''): string
    {
        if ($authenticatedProfileId < 1) return '<section class="nwotv-dashboard"><p>Sign in to submit a feed.</p></section>';
        $out='<section class="nwotv-dashboard nwotv-directory"><header class="nwotv-heading"><h2>Submit an IPTV feed</h2><p>Submit a public playlist you are authorized to share. Private subscription credentials must not be shared as a public channel catalog.</p></header>';
        if ($message!=='') $out.='<p role="status">'.self::h($message).'</p>';
        $out.='<form method="post" action="'.self::h($actionUrl).'"><input type="hidden" name="csrf_token" value="'.self::h($csrfToken).'"><label>Feed name <input required maxlength="120" name="feed_title"></label><label>HTTPS playlist URL <input required type="url" maxlength="2048" name="feed_url" autocomplete="off"></label><button type="submit" name="feed_action" value="submit">Submit for approval</button></form><p>Submissions are private, pending and disabled until reviewed. A submission does not trigger a network request or import.</p></section>';
        return $out;
    }
}
