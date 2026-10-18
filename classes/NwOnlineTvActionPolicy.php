<?php
/** Independent authorization decisions. Caller must verify UNA session server-side. */
final class NwOnlineTvActionPolicy
{
    public static function allowed(string $action, bool $admin, bool $signedIn): bool
    {
        if (in_array($action, ['view_channels', 'watch_channel'], true)) return true;
        if ($action === 'report_channel') return $signedIn;
        if (in_array($action, ['sync_channels', 'review_channels', 'edit_channels', 'migrate_channels'], true)) return $admin && $signedIn;
        return false;
    }
}
