<?php
/**
 * Independent bulk-action validator. Not a web endpoint.
 * Call only after UNA Studio authorization and CSRF have been validated.
 */
final class NwOnlineTvBulkPolicy
{
    public static function channelIds(array $raw): array
    {
        if (count($raw) < 1 || count($raw) > 100) {
            throw new InvalidArgumentException('Select between 1 and 100 channels per batch.');
        }
        $ids = [];
        foreach ($raw as $value) {
            if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
                throw new InvalidArgumentException('Invalid channel identifier.');
            }
            $id = filter_var((string)$value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new InvalidArgumentException('Invalid channel identifier.');
            $ids[$id] = $id;
        }
        if (count($ids) !== count($raw)) {
            throw new InvalidArgumentException('Duplicate channel identifiers.');
        }
        return array_values($ids);
    }

    public static function applyReview(PDO $pdo, string $action, array $rawIds, bool $authorized, bool $csrfValid): int
    {
        if (!$authorized || !$csrfValid) {
            throw new RuntimeException('Access denied.');
        }
        if (!in_array($action, ['flag', 'clear'], true)) {
            throw new InvalidArgumentException('Unsupported bulk action.');
        }
        $ids = self::channelIds($rawIds);
        $value = $action === 'flag' ? 1 : 0;
        $slots = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE nw_online_tv_channels SET review_required = ?, updated_at = ? WHERE id IN ($slots)");
        $stmt->execute(array_merge([$value, time()], $ids));
        return $stmt->rowCount();
    }
    /**
     * Read-only deletion preflight. Never authorizes or performs deletion.
     * Return the exact IDs and count present at inspection time.
     */
    public static function previewDeletion(PDO $pdo, array $rawIds, bool $authorized, bool $csrfValid): array
    {
        if (!$authorized || !$csrfValid) {
            throw new RuntimeException('Access denied.');
        }
        $ids = self::channelIds($rawIds);
        $slots = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, title FROM nw_online_tv_channels WHERE id IN ($slots) ORDER BY id");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('Selection changed; refresh and select channels again.');
        }
        return [
            'count' => count($rows),
            'ids' => array_map('intval', array_column($rows, 'id')),
            'titles' => array_map('strval', array_column($rows, 'title')),
            'scope' => 'current_page_explicit_ids_only',
        ];
    }

}
