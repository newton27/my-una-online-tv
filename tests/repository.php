<?php
require_once __DIR__.'/../classes/NwOnlineTvRepository.php';
if(!extension_loaded('pdo_sqlite')) { echo "SKIP pdo_sqlite unavailable\n"; exit(0); }
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE nw_online_tv_channels(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,stream_url TEXT,stream_hash TEXT UNIQUE,source TEXT,category TEXT DEFAULT "",country_code TEXT DEFAULT "",status TEXT DEFAULT "pending",review_required INTEGER DEFAULT 0,created_at INTEGER,updated_at INTEGER)');
$db->exec('CREATE TABLE nw_online_tv_reports(id INTEGER PRIMARY KEY,resolved_at INTEGER)');
$db->exec('CREATE TABLE nw_online_tv_sync_runs(id INTEGER PRIMARY KEY,result TEXT,finished_at INTEGER)');
$repo=new NwOnlineTvRepository($db);
$x=$repo->import([['title'=>'Channel <1>','stream_url'=>'https://example.org/a.m3u8'],['title'=>'Same','stream_url'=>'https://example.org/a.m3u8']]);
if($x['added']!==1||$x['already_listed']!==1)throw new Exception('deduplication failed');
if($repo->summary()['channels']!==1)throw new Exception('summary failed');
if($repo->page('Channel')['total']!==1)throw new Exception('search failed');
if($repo->page('%')['total']!==0)throw new Exception('LIKE wildcard escaping failed');
echo "PASS repository\n";
