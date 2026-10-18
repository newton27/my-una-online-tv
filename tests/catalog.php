<?php
require_once __DIR__ . '/../classes/NwOnlineTvCatalog.php';
$playlist = "#EXTM3U\n#EXTINF:-1,Example One\nhttps://example.org/live.m3u8\n#EXTINF:-1,Duplicate\nhttps://example.org/live.m3u8\n#EXTINF:-1,Rejected\nhttp://example.org/live.m3u8\n";
$entries = NwOnlineTvCatalog::parseM3u($playlist);
if (count($entries) !== 1 || $entries[0]['title'] !== 'Example One') {
    fwrite(STDERR, "Catalog test FAILED\n");
    exit(1);
}
echo "Catalog test PASS\n";
