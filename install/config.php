<?php
/** My UNA World Online TV - independently developed module. MIT. */
defined('BX_DOL') or die('Access denied');
$aConfig = array(
    'type' => BX_DOL_MODULE_TYPE_MODULE,
    'name' => 'nw_online_tv',
    'title' => 'My UNA World Online TV',
    'note' => 'Free community television channel catalog.',
    'version' => '0.1.0',
    'vendor' => 'My UNA World',
    'compatible_with' => array('15.0.x'),
    'home_dir' => 'newton/my_una_online_tv/',
    'home_uri' => 'my-online-tv',
    'db_prefix' => 'nw_online_tv_',
    'class_prefix' => 'NwOnlineTv',
    'language_category' => 'NwOnlineTv',
    'install' => array('execute_sql' => 1, 'update_languages' => 1, 'clear_db_cache' => 1),
    'uninstall' => array('execute_sql' => 1, 'update_languages' => 1, 'clear_db_cache' => 1),
    'enable' => array('execute_sql' => 1, 'clear_db_cache' => 1),
    'disable' => array('execute_sql' => 1, 'clear_db_cache' => 1),
);
