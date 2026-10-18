<?php
/**
 * Health result recorder for the independent Online TV module.
 * This class does not perform HTTP requests, schedule jobs or delete channels.
 * Invoke only from a trusted administrator/cron checker after verifying a stream.
 */
final class NwOnlineTvHealthRecorder
{
    public static function record(PDO $db, int $channelId, bool $reachable, int $checkedAt): array
    {
        if ($channelId < 1 || $checkedAt < 1) throw new InvalidArgumentException('Invalid check record.');
        $db->beginTransaction();
        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver !== 'mysql' && $driver !== 'sqlite') throw new RuntimeException('Unsupported database.');
            $stmt = $db->prepare('SELECT id, failure_count, review_required FROM nw_online_tv_channels WHERE id = ?' . ($driver === 'mysql' ? ' FOR UPDATE' : ''));
            $stmt->execute([$channelId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Channel no longer exists.');
            $failures = $reachable ? 0 : min(255, (int)$row['failure_count'] + 1);
            // Preserve manual review flags. A later healthy check does not erase moderator decisions.
            $review = (int)$row['review_required'] || $failures >= 2 ? 1 : 0;
            $stmt = $db->prepare('UPDATE nw_online_tv_channels SET failure_count = ?, review_required = ? WHERE id = ?');
            $stmt->execute([$failures, $review, $channelId]);
            $stmt = $db->prepare('INSERT INTO nw_online_tv_health_checks (channel_id, checked_at, reachable, consecutive_failures) VALUES (?, ?, ?, ?)');
            $stmt->execute([$channelId, $checkedAt, $reachable ? 1 : 0, $failures]);
            $db->commit();
            return ['channel_id'=>$channelId,'reachable'=>$reachable,'consecutive_failures'=>$failures,'needs_review'=>$review === 1];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
