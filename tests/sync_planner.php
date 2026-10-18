<?php
require_once __DIR__.'/../classes/NwOnlineTvSyncPlanner.php';
$input="#EXTM3U\n#EXTINF:-1,Public One\nhttps://video.example.org/live.m3u8\n#EXTINF:-1,Private\nhttps://127.0.0.1/live.m3u8\n#EXTINF:-1,Non HLS\nhttps://video.example.org/player.html\n#EXTINF:-1,Duplicate\nhttps://video.example.org/live.m3u8\n";
$result=NwOnlineTvSyncPlanner::plan($input);
if(count($result['eligible'])!==1 || $result['eligible'][0]['title']!=='Public One')throw new RuntimeException('Unexpected eligible rows');
foreach(['http://video.example.org/live.m3u8','https://localhost/live.m3u8','https://user:pass@video.example.org/live.m3u8','https://video.example.org:8443/live.m3u8'] as $url)
 if(NwOnlineTvImportPolicy::normalize(['title'=>'Bad','stream_url'=>$url])!==null)throw new RuntimeException('Unsafe URL accepted');
echo "PASS sync planner\n";
