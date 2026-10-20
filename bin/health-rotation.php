<?php
/** CLI-only bounded health rotation. Default: dry-run readiness check. */
if (PHP_SAPI !== 'cli' || count($argv)>2 || !in_array($argv[1]??'', ['', '--apply'], true)) {
    fwrite(STDERR,"Usage: php health-rotation.php [--apply]\n"); exit(2);
}
chdir(dirname(__DIR__,4));
require_once 'inc/header.inc.php';
require_once __DIR__.'/../classes/NwOnlineTvHealthBatch.php';
try {
    $db=new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4',
        BX_DATABASE_USER,BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if (($argv[1]??'')!=='--apply') {
        $pending=(int)$db->query("SELECT COUNT(*) FROM nw_online_tv_channels WHERE status='pending'")->fetchColumn();
        echo json_encode(['dry_run'=>true,'pending'=>$pending,'max_checks_per_minute'=>2]),PHP_EOL;exit(0);
    }
    echo json_encode(NwOnlineTvHealthBatch::run($db,time()),JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch(Throwable $e) {
    error_log('nw online tv health CLI: '.get_class($e));
    fwrite(STDERR,"Health rotation failed; consult server logs.\n");exit(1);
}
