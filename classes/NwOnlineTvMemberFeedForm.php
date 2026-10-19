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
        $out = '<style>
.nwotv-member-form{max-width:800px;margin:0 auto;padding:12px 4px 28px;color:inherit}
.nwotv-member-form .nwotv-form-intro{font-size:15px;line-height:1.65;opacity:.82;margin:0 0 24px}
.nwotv-member-form .nwotv-form-grid{display:grid;grid-template-columns:1fr;gap:22px}
.nwotv-member-form .nwotv-field{display:flex;flex-direction:column;gap:9px;font-size:14px;font-weight:600}
.nwotv-member-form .nwotv-input{display:block;width:100%;min-width:0;box-sizing:border-box;border:1px solid #79869b;border-radius:10px;background:rgba(255,255,255,.98);color:#172033;padding:13px 16px;min-height:48px;font-size:15px;line-height:1.4}
.nwotv-member-form .nwotv-input:focus{outline:2px solid #4385e9;outline-offset:2px}
.nwotv-member-form .nwotv-help{font-size:13px;font-weight:400;opacity:.72;line-height:1.5}
.nwotv-member-form .nwotv-form-actions{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:26px}
.nwotv-member-form .nwotv-submit{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border:0;border-radius:9px;background:#2864bd;color:white;padding:12px 24px;font-size:14px;font-weight:700;cursor:pointer}
.nwotv-member-form .nwotv-submit:hover{background:#1d529f}
.nwotv-member-form .nwotv-privacy{margin:24px 0 0;padding:16px 18px;border:1px solid rgba(130,147,171,.35);border-radius:10px;line-height:1.6;font-size:13px;opacity:.9}
.nwotv-member-form .nwotv-message{padding:12px 16px;border-radius:8px;border:1px solid #708cae;margin-bottom:20px}
@media(max-width:640px){.nwotv-member-form{padding:8px 0 22px}.nwotv-member-form .nwotv-submit{width:100%}}
</style><section class="nwotv-member-form">';
        if ($authenticatedProfileId < 1) return $out.'<p>Please sign in to submit an IPTV feed.</p></section>';
        $out .= '<p class="nwotv-form-intro">Share a publicly accessible IPTV playlist that you have permission to submit. Each submission is reviewed by a webmaster before it can be enabled.</p>';
        if ($message !== '') $out .= '<p class="nwotv-message" role="status">'.self::h($message).'</p>';
        $out .= '<form method="post" action="'.self::h($actionUrl).'"><input type="hidden" name="csrf_token" value="'.self::h($csrfToken).'"><div class="nwotv-form-grid">
<label class="nwotv-field" for="nwotv-feed-name">Feed name<input class="nwotv-input" id="nwotv-feed-name" name="feed_title" type="text" maxlength="120" placeholder="Example: Community news channels" autocomplete="off" required><span class="nwotv-help">Choose a descriptive name for the webmaster to review.</span></label>
<label class="nwotv-field" for="nwotv-feed-url">HTTPS playlist URL<input class="nwotv-input" id="nwotv-feed-url" name="feed_url" type="url" maxlength="2048" inputmode="url" placeholder="https://example.org/channels.m3u" autocomplete="off" spellcheck="false" required><span class="nwotv-help">Public HTTPS playlists only. Do not enter private subscription links, passwords or account tokens.</span></label>
</div><div class="nwotv-form-actions"><button class="nwotv-submit" type="submit" name="feed_action" value="submit">Submit feed for review</button></div></form>
<div class="nwotv-privacy"><strong>What happens next?</strong> Your submission remains pending and disabled until a webmaster reviews it. No channels are imported when you submit a feed.</div></section>';
        return $out;
    }
}
