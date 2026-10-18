<?php
/**
 * Additive only. Execute from CLI after backup.
 * Inspects expected table names; refuses incompatible pre-existing tables.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require '/home/gayjax/public_html/inc/header.inc.php';
$db=new PDO('mysql:host='.BX_DATABASE_HOST.';dbname='.BX_DATABASE_NAME.';charset=utf8mb4',BX_DATABASE_USER,BX_DATABASE_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['nw_online_tv_health_checks','nw_online_tv_health_attempts'] as $table) {
    $query=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $query->execute([$table]);
    if ((int)$query->fetchColumn() > 0) { echo "PRESENT: $table (no schema change)\n"; continue; }
    if ($table === 'nw_online_tv_health_checks') {
        $db->exec('CREATE TABLE nw_online_tv_health_checks (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, channel_id INT UNSIGNED NOT NULL, checked_at INT UNSIGNED NOT NULL, reachable TINYINT UNSIGNED NOT NULL, consecutive_failures TINYINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY channel_checked (channel_id,checked_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $db->exec('CREATE TABLE nw_online_tv_health_attempts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, channel_id INT UNSIGNED NOT NULL, attempted_at INT UNSIGNED NOT NULL, PRIMARY KEY (id), KEY attempted_at (attempted_at), KEY channel_attempted (channel_id,attempted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    echo "CREATED: $table\n";
}
echo "PASS: Health schema preparation completed. Scanning not enabled.\n";
