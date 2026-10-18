<?php
require_once __DIR__ . '/../classes/NwOnlineTvDashboard.php';
$counts = NwOnlineTvDashboard::metrics(
    [['review_required' => 1], ['review_required' => 0]],
    [['resolved_at' => null], ['resolved_at' => 123]],
    [['result' => 'failed', 'finished_at' => 500], ['result' => 'completed', 'finished_at' => 1000]]
);
if ($counts['channels'] !== 2 || $counts['needs_review'] !== 1 || $counts['reports_open'] !== 1 || $counts['last_sync'] !== '1970-01-01 00:16 UTC') {
    fwrite(STDERR, "FAIL dashboard metrics\n"); exit(1);
}
$html = NwOnlineTvDashboard::render(['channels' => 1, 'last_sync' => '<script>alert(1)</script>']);
if (strpos($html, '<script>') !== false || strpos($html, '&lt;script&gt;') === false || strpos($html, 'Online TV overview') === false) {
    fwrite(STDERR, "FAIL dashboard encoding\n"); exit(1);
}
echo "PASS dashboard\n";
