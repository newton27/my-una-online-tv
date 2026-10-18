<?php
/* Isolated MariaDB batch smoke test. TEMPORARY tables shadow live tables. */
require '/home/gayjax/public_html/inc/header.inc.php';
require dirname(__DIR__) . '/classes/NwOnlineTvHealthBatch.php';
$db=new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4',BX_DATABASE_USER,BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TEMPORARY TABLE nw_online_tv_channels (id INT PRIMARY KEY,stream_url TEXT,failure_count INT DEFAULT 0,review_required INT DEFAULT 0) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE nw_online_tv_health_checks (id INT AUTO_INCREMENT PRIMARY KEY,channel_id INT,checked_at INT,reachable INT,consecutive_failures INT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE nw_online_tv_health_attempts (id INT AUTO_INCREMENT PRIMARY KEY,channel_id INT,attempted_at INT,KEY attempted_at(attempted_at)) ENGINE=InnoDB');
$db->exec("INSERT INTO nw_online_tv_channels(id,stream_url) VALUES (1,'http://example.com/a.m3u8'),(2,'http://example.com/b.m3u8'),(3,'http://example.com/c.m3u8')");
$now=time();
$a=NwOnlineTvHealthBatch::run($db,$now);
$b=NwOnlineTvHealthBatch::run($db,$now);
$count=(int)$db->query('SELECT COUNT(*) FROM nw_online_tv_health_attempts')->fetchColumn();
$flags=(int)$db->query('SELECT SUM(review_required) FROM nw_online_tv_channels')->fetchColumn();
$checks=['first batch limited to 2'=>$a['checked']===2,'inconclusive attempts counted'=>$a['inconclusive']===2,'immediate second batch skipped'=>($b['skipped']??'')==='rate_limit','attempt ledger contains exactly 2'=>$count===2,'no channels falsely flagged'=>$flags===0];
foreach($checks as $label=>$ok) echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL;
echo 'Passed '.count(array_filter($checks)).'/'.count($checks).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
