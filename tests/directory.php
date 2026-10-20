<?php
require_once __DIR__ . '/../classes/NwOnlineTvDirectory.php';
$rows = [
    ['title' => '<img src=x onerror=alert(1)>', 'category' => 'News', 'country_code' => 'US', 'status' => 'pending', 'review_required' => 1],
    ['title' => 'Example Sports', 'category' => 'Sports', 'country_code' => 'GB', 'status' => 'active', 'review_required' => 0],
    ['title' => 'Example News', 'category' => 'News', 'country_code' => 'US', 'status' => 'active', 'review_required' => 0],
    ['title' => '<script>test</script>', 'category' => 'News', 'country_code' => 'US', 'status' => 'active', 'review_required' => 0],
    ['title' => 'Adult Channel', 'category' => 'adult', 'country_code' => 'US', 'status' => 'active', 'review_required' => 0],
    ['title' => 'Registry Channel', 'category' => 'News', 'country_code' => 'US', 'status' => 'active', 'review_required' => 0, 'source' => 'source:3']
];
$result = NwOnlineTvDirectory::filter($rows, 'US', '', 1, 1);
if ($result['total'] !== 2 || count($result['items']) !== 1 || $result['pages'] !== 2) { echo "FAIL filtering\n"; exit(1); }
$last = NwOnlineTvDirectory::filter($rows, '', 'active', 999, 1);
if ($last['page'] !== 3 || $last['total'] !== 3) { echo "FAIL bounds\n"; exit(1); }
$html = NwOnlineTvDirectory::render(NwOnlineTvDirectory::filter($rows));
if (strpos($html, '<script>') !== false || strpos($html, '&lt;script&gt;') === false) { echo "FAIL html escaping\n"; exit(1); }
foreach (['Adult Channel','Registry Channel','<img','&lt;img'] as $forbidden) {
    if (strpos($html, $forbidden) !== false) { echo "FAIL public eligibility\n"; exit(1); }
}
echo "PASS directory\n";
