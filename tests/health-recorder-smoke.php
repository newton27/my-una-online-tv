<?php
/* Standalone, disposable SQLite test. Does not load UNA or touch production. */
require_once dirname(__DIR__) . '/classes/NwOnlineTvHealthRecorder.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE nw_online_tv_channels (id INTEGER PRIMARY KEY, failure_count INTEGER NOT NULL DEFAULT 0, review_required INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE nw_online_tv_health_checks (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id INTEGER, checked_at INTEGER, reachable INTEGER, consecutive_failures INTEGER)');
$db->exec('INSERT INTO nw_online_tv_channels VALUES (1,0,0),(2,0,1)');
$a=NwOnlineTvHealthRecorder::record($db,1,false,100);
$b=NwOnlineTvHealthRecorder::record($db,1,false,101);
$c=NwOnlineTvHealthRecorder::record($db,1,true,102);
$d=NwOnlineTvHealthRecorder::record($db,2,true,103);
$checks=[
'first failure not flagged'=>!$a['needs_review'] && $a['consecutive_failures']===1,
'second failure flagged'=>$b['needs_review'] && $b['consecutive_failures']===2,
'success resets count'=>$c['consecutive_failures']===0,
'manual review retained'=>$d['needs_review'],
'four history rows'=>(int)$db->query('SELECT COUNT(*) FROM nw_online_tv_health_checks')->fetchColumn()===4
];
foreach($checks as $name=>$pass) echo ($pass?'PASS: ':'FAIL: ').$name.PHP_EOL;
echo 'Passed '.count(array_filter($checks)).'/'.count($checks).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
