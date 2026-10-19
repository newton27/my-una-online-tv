<?php
/** Idempotent CLI-only registration of UNA member submission page. No feed data modified. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
chdir(dirname(__DIR__, 5));
require_once 'inc/header.inc.php';
$db = new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4', BX_DATABASE_USER, BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$page='nw_online_tv_submit_feed';
$check=$db->prepare('SELECT COUNT(*) FROM sys_objects_page WHERE object=?');
$check->execute([$page]);
if (!(int)$check->fetchColumn()) {
    $sql="INSERT INTO sys_objects_page (object,title_system,title,module,layout_id,visible_for_levels,visible_for_levels_editable,uri,url,meta_description,meta_keywords,meta_robots,cache_lifetime,cache_editable,deletable,override_class_name,override_class_file) VALUES (?,?,?,?,5,2147483647,1,?,?, '', '', '',0,1,1,'','')";
    $db->prepare($sql)->execute([$page,'Submit IPTV feed','Submit IPTV feed','nw_online_tv','online-tv-submit-feed','page.php?i=online-tv-submit-feed']);
    echo "Registered member submission page\n";
} else echo "Page already registered\n";
$check=$db->prepare("SELECT COUNT(*) FROM sys_pages_blocks WHERE object=? AND module=?");
$check->execute([$page,'nw_online_tv']);
if (!(int)$check->fetchColumn()) {
    $service=serialize(['module'=>'nw_online_tv','method'=>'get_member_feed_submission_markup']);
    $sql='INSERT INTO sys_pages_blocks (object,cell_id,module,title,designbox_id,visible_for_levels,type,content,deletable,copyable,`order`) VALUES (?,1,?, ?,11,2147483647,\'service\',?,1,0,1)';
    $db->prepare($sql)->execute([$page,'nw_online_tv','IPTV feed submission',$service]);
    echo "Registered UNA service block\n";
} else echo "Service block already registered\n";
