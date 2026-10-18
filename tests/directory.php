<?php
require_once __DIR__ . '/../classes/NwOnlineTvDirectory.php';
$rows = [
    ['title' => '<img src=x onerror=alert(1)>', 'category' => 'News', 'country_code' => 'US', 'status' => 'pending', 'review_required' => 1],
    ['title' => 'Example Sports', 'category' => 'Sports', 'country_code' => 'GB', 'status' => 'active', 'review_required' => 0],
    ['title' => 'Example News', 'category' => 'News', 'country_code' => 'US', 'status' => 'active', 'review_required' => 0]
];
$result = NwOnlineTvDirectory::filter($rows, 'US', '', 1, 1);
if ($result['total'] !== 2 || count($result['items']) !== 1 || $result['pages'] !== 2) { echo "FAIL filtering\n"; exit(1); }
$last = NwOnlineTvDirectory::filter($rows, '', 'active', 999, 1);
if ($last['page'] !== 2 || $last['total'] !== 2) { echo "FAIL bounds\n"; exit(1); }
$html = NwOnlineTvDirectory::render(NwOnlineTvDirectory::filter($rows));
if (strpos($html, '<img') !== false || strpos($html, '&lt;img') === false) { echo "FAIL html escaping\n"; exit(1); }
echo "PASS directory\n";
