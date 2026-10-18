<?php
/**
 * My UNA Online TV - CLI only health check.
 * Cron: site account, at most once per minute. Never deletes channels.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__, 4);
if (!is_file($root . '/inc/header.inc.php')) {
    fwrite(STDERR, "UNA bootstrap missing\n");
    exit(1);
}
require $root . '/inc/header.inc.php';
require dirname(__DIR__) . '/classes/NwOnlineTvHealthBatch.php';
try {
    $db = new PDO(
        'mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
        BX_DATABASE_USER, BX_DATABASE_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $report = NwOnlineTvHealthBatch::run($db, time());
    echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Health check failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
