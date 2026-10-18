<?php
/** Safe read-only channel management screen for UNA Studio. */
defined('BX_DOL') or die('Access denied');
final class NwOnlineTvChannelsStudio
{
    private static function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(PDO $db): string
    {
        require_once __DIR__ . '/NwOnlineTvRepository.php';
        $repository = new NwOnlineTvRepository($db);
        $search = trim((string)($_GET['q'] ?? ''));
        $search = substr($search, 0, 120);
        $allowedStatuses = ['', 'pending', 'active', 'inactive', 'failed'];
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, $allowedStatuses, true)) $status = '';
        $page = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $reviewOnly = ($_GET['review'] ?? '') === '1';
        $data = $repository->page($search, $status, $page, 25, $reviewOnly);
        $base = 'module.php?name=nw_online_tv&page=channels';
        $query = '&q=' . rawurlencode($search) . '&status=' . rawurlencode($status) . '&review=' . ($reviewOnly ? '1' : '0');
        $html = '<section class="nwotv-dashboard nwotv-directory"><header class="nwotv-heading"><h2>Channel management</h2><p>Select channels individually or use the page checkbox. Review the exact deletion count, then confirm once; no typing required. Actions affect only explicit selections (maximum 100 per request).</p></header>';
        // Review flags represent suspected problems, not verified unplayable streams.
        $health = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN review_required = 1 THEN 1 ELSE 0 END), 0) AS flagged FROM nw_online_tv_channels')->fetch(PDO::FETCH_ASSOC);
        $html .= '<div class="nwotv-bulk-toolbar" aria-label="Channel review summary"><strong>Total channels: ' . (int)$health['total'] . '</strong><span>Flagged for review: ' . (int)$health['flagged'] . '</span><span>Unflagged: ' . ((int)$health['total'] - (int)$health['flagged']) . '</span></div>';
        // Health-check history is optional until the additive migration is applied.
        $table = $db->query("SHOW TABLES LIKE 'nw_online_tv_health_checks'")->fetchColumn();
        if ($table) {
            $healthCounts = $db->query('SELECT SUM(last.reachable = 1) AS reachable, SUM(last.reachable = 0) AS unreachable, COUNT(*) AS checked FROM (SELECT h.channel_id, h.reachable FROM nw_online_tv_health_checks h INNER JOIN (SELECT channel_id, MAX(id) AS latest_id FROM nw_online_tv_health_checks GROUP BY channel_id) last_id ON last_id.latest_id = h.id INNER JOIN nw_online_tv_channels c ON c.id = h.channel_id) last')->fetch(PDO::FETCH_ASSOC);
            $checked = (int)$healthCounts['checked'];
            $html .= '<div class="nwotv-bulk-toolbar" aria-label="Availability checks"><strong>Reachable URL: ' . (int)$healthCounts['reachable'] . '</strong><span>Last check failed: ' . (int)$healthCounts['unreachable'] . '</span><span>Unchecked: ' . max(0, (int)$health['total'] - $checked) . '</span></div>';
            $html .= '<p class="nwotv-results">URL availability does not prove that a stream plays. Failed checks require review; they do not trigger deletion.</p>';
        } else {
            $html .= '<p class="nwotv-results">Automatic health-check statistics will appear after the health-history migration. Scanning remains disabled.</p>';
        }
        $html .= '<form class="nwotv-filters" method="get" action="module.php">';
        $html .= '<input type="hidden" name="name" value="nw_online_tv"><input type="hidden" name="page" value="channels">';
        $html .= '<label>Search channels <input type="search" name="q" maxlength="120" value="' . self::h($search) . '" placeholder="Channel title, category, or country"></label>';
        $html .= '<label>Status <select name="status">';
        foreach ($allowedStatuses as $option) {
            $label = $option === '' ? 'All statuses' : ucfirst($option);
            $html .= '<option value="' . self::h($option) . '"' . ($option === $status ? ' selected' : '') . '>' . self::h($label) . '</option>';
        }
        $html .= '</select></label><label>Review status <select name="review"><option value="0"' . (!$reviewOnly ? ' selected' : '') . '>All channels</option><option value="1"' . ($reviewOnly ? ' selected' : '') . '>Needs review only</option></select></label><button type="submit">Filter channels</button></form>';
        $html .= '<p class="nwotv-results">' . (int)$data['total'] . ' matching channels — page ' . (int)$data['page'] . ' of ' . (int)$data['pages'] . '</p>';
        if (!$data['items']) {
            $html .= '<div class="nwotv-empty">No channels match these filters. No records were modified.</div>';
        } else {
            $token = BxDolForm::getCsrfToken();
            $html .= '<form method="post" action="' . self::h($base . $query . '&p=' . $data['page']) . '" class="nwotv-bulk-form">';
            $html .= '<input type="hidden" name="csrf_token" value="' . self::h((string)$token) . '">';
            $html .= '<div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th scope="col"><input type="checkbox" id="nwotv-select-all" aria-label="Select all channels on current page"></th><th scope="col">Channel</th><th scope="col">Category</th><th scope="col">Country</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead><tbody>';
            foreach ($data['items'] as $row) {
                $html .= '<tr><td><input type="checkbox" class="nwotv-row-check" name="channel_ids[]" value="' . (int)$row['id'] . '" aria-label="Select channel"></td><td>' . self::h($row['title']) . '</td><td>' . self::h($row['category']) . '</td><td>' . self::h($row['country_code']) . '</td><td>' . self::h($row['status']) . '</td><td>' . (!empty($row['review_required']) ? 'Needs review' : '—') . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        if (!empty($data['items'])) {
            $html .= '<div class="nwotv-bulk-toolbar"><span id="nwotv-selection-count">0 selected</span>';
            $html .= '<button type="submit" name="bulk_action" value="flag">Flag for review</button>';
            $html .= '<button type="submit" name="bulk_action" value="clear">Clear flag</button>';
            $html .= '<button type="submit" name="bulk_action" value="preview_delete">Preview deletion</button></div></form>';
        }
        $html .= '<nav class="nwotv-pagination" aria-label="Channel pages">';
        if ($data['page'] > 1) $html .= '<a href="' . self::h($base . $query . '&p=' . ($data['page'] - 1)) . '">Previous</a>';
        if ($data['page'] < $data['pages']) $html .= '<a href="' . self::h($base . $query . '&p=' . ($data['page'] + 1)) . '">Next</a>';
        return $html . '</nav></section><script>(function(){const root=document.querySelector(".nwotv-directory");if(!root)return;const all=root.querySelector("#nwotv-select-all"),rows=Array.from(root.querySelectorAll(".nwotv-row-check")),out=root.querySelector("#nwotv-selection-count");if(!all||!out)return;function refresh(){const n=rows.filter(r=>r.checked).length;out.textContent=n+" selected on this page";all.checked=rows.length>0&&n===rows.length;all.indeterminate=n>0&&n<rows.length;}all.addEventListener("change",()=>{rows.forEach(r=>r.checked=all.checked);refresh()});rows.forEach(r=>r.addEventListener("change",refresh));refresh();})();</script>';
    }
}
