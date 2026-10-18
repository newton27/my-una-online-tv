<?php
/**
 * Independent review policy: two consecutive failures request human review.
 * Does not delete, hide or disable a channel.
 */
final class NwOnlineTvReview
{
    public static function nextState(array $channel, bool $reachable): array
    {
        $failures = max(0, min(255, (int)($channel['failure_count'] ?? 0)));
        $review = !empty($channel['review_required']) ? 1 : 0;
        if ($reachable) {
            $failures = 0;
        } else {
            $failures = min(255, $failures + 1);
            if ($failures >= 2) $review = 1;
        }
        return ['failure_count' => $failures, 'review_required' => $review];
    }
    public static function validateReportReason(string $reason): string
    {
        $reason = trim($reason);
        if (!in_array($reason, ['not_playing', 'wrong_channel', 'incorrect_details', 'other'], true)) {
            throw new InvalidArgumentException('Unsupported report reason');
        }
        return $reason;
    }
}
