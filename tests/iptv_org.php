<?php
require_once __DIR__.'/../classes/NwOnlineTvIptvOrg.php';
$meta=[];
for ($i=0;$i<101;$i++) $meta[]=['id'=>'Test'.$i.'.us','is_nsfw'=>false,'closed'=>null,'replaced_by'=>null,'categories'=>['general']];
$meta[1]['is_nsfw']=true;
$meta[2]['closed']='2025-01-01';
$meta[3]['replaced_by']='Other.us';
$meta[4]['categories']=['adult'];
$playlist="#EXTM3U\n";
for ($i=0;$i<6;$i++) $playlist.='#EXTINF:-1 tvg-id="Test'.$i.'.us@HD" group-title="General",Test '.$i."\nhttps://example.org/".$i.".m3u8\n";
$r=NwOnlineTvIptvOrg::filter($playlist,json_encode($meta,JSON_THROW_ON_ERROR));
if ($r['metadata_kept']!==2 || $r['metadata_excluded']!==4 || substr_count($r['playlist'],'#EXTINF:')!==2)
    throw new RuntimeException('IPTV-org metadata exclusion regression');
echo "PASS IPTV-org audience gate\n";
