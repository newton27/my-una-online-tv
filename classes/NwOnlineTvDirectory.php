<?php
/**
 * My UNA World Online TV — original, standalone channel directory presenter.
 * Caller is responsible for authorization and supplying bounded channel data.
 */
final class NwOnlineTvDirectory
{
    public static function filter(array $channels, string $query = '', string $status = '', int $page = 1, int $perPage = 25): array
    {
        $query = trim($query);
        $status = trim($status);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $matches = [];
        foreach ($channels as $channel) {
            if (!is_array($channel)) continue;
            $title = (string)($channel['title'] ?? '');
            $category = (string)($channel['category'] ?? '');
            $country = (string)($channel['country_code'] ?? '');
            if ($query !== '' && stripos($title . ' ' . $category . ' ' . $country, $query) === false) continue;
            if ($status !== '' && (string)($channel['status'] ?? '') !== $status) continue;
            $matches[] = $channel;
        }
        $total = count($matches);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        return [
            'items' => array_slice($matches, ($page - 1) * $perPage, $perPage),
            'total' => $total, 'page' => $page, 'pages' => $pages,
        ];
    }

    public static function render(array $result): string
    {
        $total = max(0, (int)($result['total'] ?? 0));
        $page = max(1, (int)($result['page'] ?? 1));
        $pages = max(1, (int)($result['pages'] ?? 1));
        $out = '<section class="nwotv-directory" aria-label="Online TV channels">';
        $out .= '<header><h2>Channel directory</h2><p>' . $total . ' channels · Page ' . $page . ' of ' . $pages . '</p></header>';
        $out .= '<div class="nwotv-table-scroll"><table><thead><tr><th scope="col">Channel</th><th scope="col">Category</th><th scope="col">Country</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead><tbody>';
        foreach (($result['items'] ?? []) as $channel) {
            if (!is_array($channel)) continue;
            $cells = [
                (string)($channel['title'] ?? ''),
                (string)($channel['category'] ?? ''),
                (string)($channel['country_code'] ?? ''),
                (string)($channel['status'] ?? 'pending'),
                !empty($channel['review_required']) ? 'Needs review' : '—',
            ];
            $out .= '<tr>';
            foreach ($cells as $cell) $out .= '<td>' . self::escape($cell) . '</td>';
            $out .= '</tr>';
        }
        if (empty($result['items'])) $out .= '<tr><td colspan="5">No channels match the selected filters.</td></tr>';
        return $out . '</tbody></table></div></section>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
