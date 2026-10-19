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
        $registry=$db->query('SELECT id,title,playlist_url,origin,approval_status,enabled,submitted_profile_id FROM nw_online_tv_sources ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        $editId=filter_var($_GET['edit_source']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
        $edit=null;foreach($registry as $item)if((int)$item['id']===$editId && $item['origin']==='webmaster')$edit=$item;
        $rows=$db->query('SELECT source,result,discovered,added,finished_at FROM nw_online_tv_sync_runs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $current=$db->query('SELECT source,COUNT(*) AS channels FROM nw_online_tv_channels GROUP BY source ORDER BY channels DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
        $html='<section class="nwotv-dashboard nwotv-directory"><header class="nwotv-heading"><h2>Channel synchronization</h2><p>Review catalog sources and recent results before importing channels. No existing channel edits are replaced.</p></header>';
        $html.='<div class="nwotv-bulk-toolbar"><strong>Automatic catalog import: Not enabled</strong><span>Health URL checks run separately and never delete listings.</span></div>';
        $html.='<form class="nwotv-filters" method="get" action="module.php"><input type="hidden" name="name" value="nw_online_tv"><input type="hidden" name="page" value="sync"><label>Catalog source <select name="source">';
        foreach($sources as $key=>$label)$html.='<option value="'.self::h($key).'"'.($source===$key?' selected':'').'>'.self::h($label).'</option>';
        $html.='</select></label><button type="submit">View source</button></form>';
        $html.='<p class="nwotv-results">Selected source: <strong>'.self::h($sources[$source]).'</strong>. Import preview and apply controls will be available after isolated source-fetch and database-preservation tests pass.</p>';
        $html.='<h3>IPTV source list</h3><p class="nwotv-results">Sources require approval. Enabling a source does not start an import; external playlist fetching is not active yet.</p>';
        $html.='<div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Name</th><th>Origin</th><th>Approval</th><th>Enabled</th><th>Actions</th></tr></thead><tbody>';
        foreach($registry as $r){
            $html.='<tr><td>'.self::h($r['title']).'</td><td>'.self::h($r['origin']).'</td><td>'.self::h($r['approval_status']).'</td><td>'.((int)$r['enabled']?'Yes':'No').'</td><td><div class="nwotv-source-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
            if($r['origin']==='webmaster')$html.='<a class="nwotv-source-action-link" href="module.php?name=nw_online_tv&amp;page=sync&amp;edit_source='.(int)$r['id'].'" style="display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:5px 14px;border-radius:7px;border:1px solid #617186;text-decoration:none">Edit</a>';
            foreach(['approve'=>'Approve','reject'=>'Reject','enable'=>'Enable','disable'=>'Disable'] as $action=>$label){
                if(($action==='approve'||$action==='reject')&&$r['approval_status']!=='pending')continue;
                if($action==='enable'&&($r['approval_status']!=='approved'||$r['enabled']))continue;
                if($action==='disable'&&!$r['enabled'])continue;
                $html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><button type="submit" name="source_action" value="'.$action.'">'.$label.'</button></form> ';
            }
            $html.='</div></td></tr>';
        }
        if(!$registry)$html.='<tr><td colspan="5">No IPTV sources registered yet.</td></tr>';
        $html.='</tbody></table></div><h3>'.($edit?'Edit webmaster source':'Add IPTV source').'</h3>';
        $html.='<form class="nwotv-filters" method="post" action="module.php?name=nw_online_tv&amp;page=sync"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)($edit['id']??0).'"><label>Source name<input type="text" name="source_title" maxlength="120" required value="'.self::h($edit['title']??'').'"></label><label>HTTPS playlist URL<input type="url" name="source_url" maxlength="2048" '.($edit?'placeholder="Leave blank to retain saved URL"':'required placeholder="https://example.org/playlist.m3u"').' value=""></label><button type="submit" name="source_action" value="save">Save source</button>'.($edit?'<a href="module.php?name=nw_online_tv&amp;page=sync" class="nwotv-confirm-cancel">Cancel editing</a>':'').'</form>';
        $html.='<h3>Current channel inventory</h3><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Source</th><th>Channels</th></tr></thead><tbody>';
        foreach($current as $r)$html.='<tr><td>'.self::h($r['source']).'</td><td>'.(int)$r['channels'].'</td></tr>';
        if(!$current)$html.='<tr><td colspan="2">No channels imported yet.</td></tr>';
        $html.='</tbody></table></div><h3>Last 10 synchronization runs</h3><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Source</th><th>Result</th><th>Discovered</th><th>Added</th><th>Finished (UTC)</th></tr></thead><tbody>';
        foreach($rows as $r)$html.='<tr><td>'.self::h($r['source']).'</td><td>'.self::h($r['result']).'</td><td>'.(int)$r['discovered'].'</td><td>'.(int)$r['added'].'</td><td>'.(!empty($r['finished_at'])?gmdate('Y-m-d H:i',(int)$r['finished_at']):'—').'</td></tr>';
        if(!$rows)$html.='<tr><td colspan="5">No synchronization runs recorded.</td></tr>';
        return $html.'</tbody></table></div></section>';
    }
}
