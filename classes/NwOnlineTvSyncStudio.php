<?php
/** Safe read-only Studio sync screen. No remote URLs fetched or channel writes. */
defined('BX_DOL') or die('Access denied');
final class NwOnlineTvSyncStudio
{
    private static function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function render(PDO $db): string
    {
        $sources=['iptv-org'=>'iptv-org','free-tv'=>'Free-TV'];
        $source=(string)($_GET['source']??'iptv-org');
        if(!isset($sources[$source]))$source='iptv-org';
        $rows=$db->query('SELECT source,result,discovered,added,finished_at FROM nw_online_tv_sync_runs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $current=$db->query('SELECT source,COUNT(*) AS channels FROM nw_online_tv_channels GROUP BY source ORDER BY channels DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
        $html='<section class="nwotv-dashboard nwotv-directory"><header class="nwotv-heading"><h2>Channel synchronization</h2><p>Review catalog sources and recent results before importing channels. No existing channel edits are replaced.</p></header>';
        $html.='<div class="nwotv-bulk-toolbar"><strong>Automatic catalog import: Not enabled</strong><span>Health URL checks run separately and never delete listings.</span></div>';
        $html.='<form class="nwotv-filters" method="get" action="module.php"><input type="hidden" name="name" value="nw_online_tv"><input type="hidden" name="page" value="sync"><label>Catalog source <select name="source">';
        foreach($sources as $key=>$label)$html.='<option value="'.self::h($key).'"'.($source===$key?' selected':'').'>'.self::h($label).'</option>';
        $html.='</select></label><button type="submit">View source</button></form>';
        $html.='<p class="nwotv-results">Selected source: <strong>'.self::h($sources[$source]).'</strong>. Import preview and apply controls will be available after isolated source-fetch and database-preservation tests pass.</p>';
        $html.='<h3>Current channel inventory</h3><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Source</th><th>Channels</th></tr></thead><tbody>';
        foreach($current as $r)$html.='<tr><td>'.self::h($r['source']).'</td><td>'.(int)$r['channels'].'</td></tr>';
        if(!$current)$html.='<tr><td colspan="2">No channels imported yet.</td></tr>';
        $html.='</tbody></table></div><h3>Last 10 synchronization runs</h3><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Source</th><th>Result</th><th>Discovered</th><th>Added</th><th>Finished (UTC)</th></tr></thead><tbody>';
        foreach($rows as $r)$html.='<tr><td>'.self::h($r['source']).'</td><td>'.self::h($r['result']).'</td><td>'.(int)$r['discovered'].'</td><td>'.(int)$r['added'].'</td><td>'.(!empty($r['finished_at'])?gmdate('Y-m-d H:i',(int)$r['finished_at']):'—').'</td></tr>';
        if(!$rows)$html.='<tr><td colspan="5">No synchronization runs recorded.</td></tr>';
        return $html.'</tbody></table></div></section>';
    }
}
