<?php
$paths=['classes/NwOnlineTvModule.php','classes/NwOnlineTvStudioPage.php','classes/NwOnlineTvDashboard.php','classes/NwOnlineTvDirectory.php'];
foreach($paths as $path){if(!is_file(__DIR__.'/../'.$path))throw new RuntimeException('Missing module component '.$path);}
$module=file_get_contents(__DIR__.'/../classes/NwOnlineTvModule.php');
$studio=file_get_contents(__DIR__.'/../classes/NwOnlineTvStudioPage.php');
if(strpos($module,'extends BxDolModule')===false||strpos($studio,'extends BxTemplStudioModule')===false)throw new RuntimeException('Expected native base class missing');
echo "PASS module structural checks (not UNA runtime validation)\n";
