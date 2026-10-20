<?php
require_once __DIR__.'/../classes/NwOnlineTvCatalog.php';
$playlist="#EXTM3U\n"
    .'#EXTINF:-1 group-title="Adult",Adult Sample'."\n".'https://example.org/a.m3u8'."\n"
    .'#EXTINF:-1 group-title="News",Weather Sample'."\n".'https://example.org/b.m3u8'."\n"
    .'#EXTINF:-1 group-title="News",Closed Channel'."\n".'https://example.org/c.m3u8'."\n";
$rows=NwOnlineTvCatalog::parseM3u($playlist);
if(count($rows)!==1 || $rows[0]['title']!=='Weather Sample') {
    fwrite(STDERR,"FAIL general-audience metadata filter\n"); exit(1);
}
echo "PASS general-audience metadata filter\n";
