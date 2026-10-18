<?php
require_once __DIR__.'/../classes/NwOnlineTvMigrationPreview.php';
$url='https://video.example.org/news.m3u8';$hash=hash('sha256',$url);
$rows=[['title'=>'Already known','stream_url'=>$url],['title'=>'New','stream_url'=>'https://video.example.org/sports.m3u8'],['title'=>'Bad','stream_url'=>'http://video.example.org/legacy.m3u8']];
$r=NwOnlineTvMigrationPreview::review($rows,[$hash]);
if($r['scanned']!==3 || $r['eligible']!==1 || $r['duplicate']!==1 || $r['rejected']!==1) throw new RuntimeException('Migration preview failed');
if(count($r['preview'])!==1 || $r['preview'][0]['title']!=='New')throw new RuntimeException('Unexpected migration candidate');
echo "PASS migration preview\n";
