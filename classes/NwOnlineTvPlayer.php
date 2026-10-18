<?php
/**
 * Independent public-channel player markup presenter.
 * Viewer embeds only explicitly validated HTTPS HLS URLs.
 */
require_once __DIR__.'/NwOnlineTvImportPolicy.php';
final class NwOnlineTvPlayer
{
    public static function render(array $channel): string
    {
        $item=NwOnlineTvImportPolicy::normalize($channel);
        if($item===null)return '<p class="nwotv-player-error" role="alert">Channel stream unavailable.</p>';
        // Validate the channel via normalize(), but escape the original label for display.
        // Stripping tags before escaping hides literal text and breaks output encoding checks.
        $name=htmlspecialchars(trim((string)$channel['title']),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $url=htmlspecialchars($item['stream_url'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        // Native HTML5 HLS support depends on browser. A separate player library may be required.
        return '<section class="nwotv-player" aria-label="TV player"><h2>'.$name.'</h2><video controls playsinline preload="none" crossorigin="anonymous"><source src="'.$url.'" type="application/vnd.apple.mpegurl">Your browser does not support this video source.</video></section>';
    }
}
