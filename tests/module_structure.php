<?php
$paths=['classes/NwOnlineTvModule.php','classes/NwOnlineTvStudioPage.php','classes/NwOnlineTvDashboard.php','classes/NwOnlineTvDirectory.php'];
foreach($paths as $path){if(!is_file(__DIR__.'/../'.$path))throw new RuntimeException('Missing module component '.$path);}
$module=file_get_contents(__DIR__.'/../classes/NwOnlineTvModule.php');
$studio=file_get_contents(__DIR__.'/../classes/NwOnlineTvStudioPage.php');
if(strpos($module,'extends BxDolModule')===false||strpos($studio,'extends BxTemplStudioModule')===false)throw new RuntimeException('Expected native base class missing');
$install=file_get_contents(__DIR__.'/../install/sql/install.sql');
$uninstall=file_get_contents(__DIR__.'/../install/sql/uninstall.sql');
foreach(['sys_objects_page','sys_pages_blocks','nw_online_tv_submit_feed','get_member_feed_submission_markup'] as $needle){
    if(strpos($install,$needle)===false)throw new RuntimeException('Native install missing UNA feed page registration: '.$needle);
}
foreach(['sys_objects_page','sys_pages_blocks','nw_online_tv_submit_feed'] as $needle){
    if(strpos($uninstall,$needle)===false)throw new RuntimeException('Native uninstall missing feed page cleanup: '.$needle);
}
echo "PASS module structural checks (not UNA runtime validation)\n";
