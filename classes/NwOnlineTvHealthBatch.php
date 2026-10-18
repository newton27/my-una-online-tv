<?php
/**
 * Explicitly invoked health batch; no scheduler or web endpoint is installed.
 * At most two real probes per 60-second window, including inconclusive probes.
 * Uses an advisory MySQL lock to prevent concurrent workers.
 */
require_once __DIR__ . '/NwOnlineTvStreamProbe.php';
require_once __DIR__ . '/NwOnlineTvHealthRecorder.php';
final class NwOnlineTvHealthBatch
{
    public static function run(PDO $db, int $now): array
    {
        if ($now < 1 || abs(time() - $now) > 5 || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('MariaDB and a current timestamp are required.');
        }
        $lock = $db->query("SELECT GET_LOCK('nw_online_tv_health_batch', 0)")->fetchColumn();
        if ((int)$lock !== 1) return ['checked'=>0,'skipped'=>'busy'];
        try {
            // An explicit check ledger records every attempt, including inconclusive ones.
            // The extra ledger table must be created by an approved migration first.
            $recent = $db->prepare('SELECT COUNT(*) FROM nw_online_tv_health_attempts WHERE attempted_at > ?');
            $recent->execute([$now - 60]);
            $capacity = max(0, 2 - (int)$recent->fetchColumn());
            if (!$capacity) return ['checked'=>0,'skipped'=>'rate_limit'];
            $candidates = $db->prepare('SELECT c.id, c.stream_url FROM nw_online_tv_channels c LEFT JOIN (SELECT channel_id, MAX(attempted_at) AS last_attempt FROM nw_online_tv_health_attempts GROUP BY channel_id) h ON h.channel_id = c.id ORDER BY COALESCE(h.last_attempt, 0), c.id LIMIT ' . $capacity);
            $candidates->execute();
            $rows = $candidates->fetchAll(PDO::FETCH_ASSOC);
            $totals = ['checked'=>0,'reachable'=>0,'failed'=>0,'inconclusive'=>0];
            foreach ($rows as $row) {
                // Reserve a rate-limit slot before initiating external traffic.
                $attempt = $db->prepare('INSERT INTO nw_online_tv_health_attempts (channel_id, attempted_at) VALUES (?, ?)');
                $attempt->execute([(int)$row['id'], $now]);
                $result = NwOnlineTvStreamProbe::check((string)$row['stream_url']);
                $totals['checked']++;
                if ($result['result'] === 'unknown') {
                    $totals['inconclusive']++;
                    continue;
                }
                $reachable = $result['result'] === 'reachable';
                NwOnlineTvHealthRecorder::record($db, (int)$row['id'], $reachable, $now);
                $totals[$reachable ? 'reachable' : 'failed']++;
            }
            return $totals;
        } finally {
            $db->query("SELECT RELEASE_LOCK('nw_online_tv_health_batch')");
        }
    }
}
