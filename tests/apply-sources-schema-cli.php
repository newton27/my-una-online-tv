<?php
/* CLI-only additive sources migration, never touches channel records. */
if(PHP_SAPI!=='cli')exit(1);
require '/home/gayjax/public_html/inc/header.inc.php';
$db=new mysqli(BX_DATABASE_HOST,BX_DATABASE_USER,BX_DATABASE_PASS,BX_DATABASE_NAME);
if($db->connect_errno)throw new RuntimeException('Database connection failed.');
$sql=file_get_contents(__DIR__.'/../install/sql/upgrade-sources.sql');
if(!$db->query($sql))throw new RuntimeException('Source table creation failed.');
$r=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='nw_online_tv_sources'");
if((int)$r->fetch_row()[0]!==1)throw new RuntimeException('Missing table after migration.');
echo "PASS: source registry present; no channels modified\n";
