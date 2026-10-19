<?php
/** Idempotent schema upgrade for reversible IPTV source archiving. */
require_once dirname(__DIR__,4).'/inc/header.inc.php';
$db=new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4',BX_DATABASE_USER,BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS nw_online_tv_source_archives (source_id INT UNSIGNED NOT NULL PRIMARY KEY, archived_at INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Source archive table ready\n";
