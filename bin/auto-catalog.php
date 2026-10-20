<?php
/** Run: php bin/auto-catalog.php SOURCE_ID [--apply]. Default is read-only. */
if (PHP_SAPI!=='cli') exit(2);
$sourceId=filter_var($argv[1]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$flag=$argv[2]??'';
if (!$sourceId || !in_array($flag,['','--apply'],true)) {
    fwrite(STDERR,"Usage: php bin/auto-catalog.php SOURCE_ID [--apply]\n");
    exit(2);
}
chdir(dirname(__DIR__,4));
require_once 'inc/header.inc.php';
require_once __DIR__.'/../classes/NwOnlineTvAutoCatalog.php';
try {
    $db=new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4',
        BX_DATABASE_USER,BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $result=NwOnlineTvAutoCatalog::run($db,(int)$sourceId,$flag!=='--apply');
    echo json_encode($result,JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch(Throwable $e) {
    error_log('nw_online_tv CLI auto catalog: '.get_class($e));
    fwrite(STDERR,"Catalog run rejected; consult server error log.\n");
    exit(1);
}
