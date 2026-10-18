<?php
/**
 * My UNA World Online TV — independent dashboard presenter.
 * This class is framework-agnostic so it can be tested before UNA integration.
 * It displays supplied data only and does not perform database writes.
 */
final class NwOnlineTvDashboard
{
    public static function metrics(array $channels, array $reports, array $syncRuns): array
    {
        $counts = ['channels' => count($channels), 'needs_review' => 0, 'reports_open' => 0, 'last_sync' => 'Never'];
        foreach ($channels as $channel) {
            if (!empty($channel['review_required'])) $counts['needs_review']++;
        }
        foreach ($reports as $report) {
            if (empty($report['resolved_at'])) $counts['reports_open']++;
        }
        foreach ($syncRuns as $run) {
            if (($run['result'] ?? '') !== 'completed' || empty($run['finished_at'])) continue;
            $timestamp = (int)$run['finished_at'];
            if ($timestamp > 0 && ($counts['_last_timestamp'] ?? 0) < $timestamp) {
                $counts['_last_timestamp'] = $timestamp;
                $counts['last_sync'] = gmdate('Y-m-d H:i', $timestamp) . ' UTC';
            }
        }
        unset($counts['_last_timestamp']);
        return $counts;
    }

    public static function render(array $metrics): string
    {
        $cards = [
            ['Channels', (string)max(0, (int)($metrics['channels'] ?? 0))],
            ['Needs review', (string)max(0, (int)($metrics['needs_review'] ?? 0))],
            ['Open reports', (string)max(0, (int)($metrics['reports_open'] ?? 0))],
            ['Last successful sync', (string)($metrics['last_sync'] ?? 'Never')],
        ];
        $html = '<section class="nwotv-dashboard" aria-label="Online TV overview"><header class="nwotv-heading"><h2>Online TV overview</h2><p>Channel catalog and review activity</p></header><div class="nwotv-card-grid">';
        foreach ($cards as [$label, $value]) {
            $html .= '<article class="nwotv-card"><h3>' . self::escape($label) . '</h3><strong>' . self::escape($value) . '</strong></article>';
        }
        return $html . '</div></section>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
