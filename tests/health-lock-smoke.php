<?php
/* Read-only concurrency check of advisory lock used by health batch. */
require '/home/gayjax/public_html/inc/header.inc.php';
$dsn='mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
$a=new PDO($dsn,BX_DATABASE_USER,BX_DATABASE_PASS,$options);
$b=new PDO($dsn,BX_DATABASE_USER,BX_DATABASE_PASS,$options);
$name='nw_online_tv_health_batch';
$held=(int)$a->query("SELECT GET_LOCK('nw_online_tv_health_batch', 0)")->fetchColumn();
$blocked=(int)$b->query("SELECT GET_LOCK('nw_online_tv_health_batch', 0)")->fetchColumn();
$released=(int)$a->query("SELECT RELEASE_LOCK('nw_online_tv_health_batch')")->fetchColumn();
$acquired=(int)$b->query("SELECT GET_LOCK('nw_online_tv_health_batch', 0)")->fetchColumn();
if($acquired===1)$b->query("SELECT RELEASE_LOCK('nw_online_tv_health_batch')");
$checks=['first worker acquires'=>$held===1,'second worker blocked'=>$blocked===0,'lock released'=>$released===1,'second acquires after release'=>$acquired===1];
foreach($checks as $label=>$ok)echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL;
echo 'Passed '.count(array_filter($checks)).'/'.count($checks).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
