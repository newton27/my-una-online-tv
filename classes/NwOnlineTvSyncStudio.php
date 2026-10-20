<?php
/** Safe read-only Studio sync screen. No remote URLs fetched or channel writes. */
defined('BX_DOL') or die('Access denied');
final class NwOnlineTvSyncStudio
{
    private static function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function render(PDO $db): string
    {
        $registry=$db->query('SELECT s.id,s.title,s.playlist_url,s.origin,s.approval_status,s.enabled,s.submitted_profile_id, a.archived_at,COALESCE(cl.classification,\'unclassified\') AS classification FROM nw_online_tv_sources s LEFT JOIN nw_online_tv_source_archives a ON a.source_id=s.id LEFT JOIN nw_online_tv_source_classification cl ON cl.source_id=s.id ORDER BY s.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        $sourceView=(string)($_GET['source_view']??'active');
        if(!in_array($sourceView,['active','archived','all'],true))$sourceView='active';
        $editId=filter_var($_GET['edit_source']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
        $edit=null;foreach($registry as $item)if((int)$item['id']===$editId && $item['origin']==='webmaster')$edit=$item;
        $rows=$db->query('SELECT source,result,discovered,added,finished_at FROM nw_online_tv_sync_runs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $current=$db->query('SELECT source,COUNT(*) AS channels FROM nw_online_tv_channels GROUP BY source ORDER BY channels DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
        $html='<section class="nwotv-dashboard nwotv-directory"><header class="nwotv-heading"><h2>Channel synchronization</h2><p>Review catalog sources and recent results before importing channels. No existing channel edits are replaced.</p></header>';
        $html.='<div class="nwotv-bulk-toolbar"><strong>Automatic catalog import: Not enabled</strong><span>Health URL checks run separately and never delete listings.</span></div>';
        $html.='<h3>IPTV source list</h3><p class="nwotv-results">Archive an unused source to disable it without deleting channels. Restore when needed. Approved webmaster playlists can be previewed; member feeds stay private.</p>';
        $html.='<form method="get" action="module.php" style="display:flex;gap:12px;align-items:center;margin:14px 0"><input type="hidden" name="name" value="nw_online_tv"><input type="hidden" name="page" value="sync"><label>Show sources <select name="source_view" class="nwotv-source-view">';foreach(['active'=>'Active','archived'=>'Archived','all'=>'All'] as $key=>$name)$html.='<option value="'.$key.'"'.($sourceView===$key?' selected':'').'>'.$name.'</option>';$html.='</select></label><button type="submit" style="padding:8px 16px;border:1px solid #718299;border-radius:8px">Filter sources</button></form>';
        $html.='<div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Name</th><th>Origin</th><th>Approval</th><th>Enabled</th><th>Audience</th><th>Actions</th></tr></thead><tbody>';
        $visible=0; foreach($registry as $r){if(($sourceView==='active'&&$r['archived_at'])||($sourceView==='archived'&&!$r['archived_at']))continue;$visible++;
            $html.='<tr><td>'.self::h($r['title']).($r['archived_at']?' <small>(Archived)</small>':'').'</td><td>'.self::h($r['origin']).'</td><td>'.self::h($r['approval_status']).'</td><td>'.((int)$r['enabled']?'Yes':'No').'</td><td>'.self::h(ucfirst($r['classification'])).'</td><td><div class="nwotv-source-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
            $html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;gap:6px;align-items:center;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><label class="nwotv-source-audience" style="display:inline-flex;align-items:center;gap:6px">Audience <select name="source_classification" style="min-height:34px;border:1px solid #617186;border-radius:7px;padding:4px 8px;background-color:var(--bx-color-box,#252932);color:var(--bx-color-text,#e6ebf2);color-scheme:normal">';
            foreach(['unclassified'=>'Unclassified','adult'=>'Adult','general'=>'General audience'] as $audience=>$label)$html.='<option value="'.$audience.'"'.($r['classification']===$audience?' selected':'').'>'.$label.'</option>';
            $html.='</select></label><button type="submit" name="source_action" value="classify" style="min-height:34px;padding:5px 12px;border:1px solid #617186;border-radius:7px;cursor:pointer">Save audience</button></form>';
            if(!$r['archived_at'] && $r['origin']==='webmaster' && $r['approval_status']==='approved' && (int)$r['enabled']===1)$html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><button type="submit" name="source_action" value="preview_source" style="min-height:34px;padding:5px 14px;border:1px solid #617186;border-radius:7px;cursor:pointer">Preview Channels</button></form>';
            if(!$r['archived_at'] && $r['origin']==='webmaster')$html.='<a class="nwotv-source-action-link" href="module.php?name=nw_online_tv&amp;page=sync&amp;edit_source='.(int)$r['id'].'" style="display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:5px 14px;border-radius:7px;border:1px solid #617186;text-decoration:none">Edit</a>';
            foreach(['approve'=>'Approve','reject'=>'Reject','enable'=>'Enable','disable'=>'Disable'] as $action=>$label){
                if(($action==='approve'||$action==='reject')&&$r['approval_status']!=='pending')continue;
                if($action==='enable'&&($r['approval_status']!=='approved'||$r['enabled']||$r['archived_at']))continue;
                if($action==='disable'&&!$r['enabled'])continue;
                $html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><button type="submit" name="source_action" value="'.$action.'" style="min-height:34px;padding:5px 14px;border:1px solid #617186;border-radius:7px;cursor:pointer">'.$label.'</button></form> ';
            }
            $html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><button type="submit" name="source_action" value="'.($r['archived_at']?'restore':'archive').'" style="min-height:34px;padding:5px 14px;border:1px solid #617186;border-radius:7px;cursor:pointer">'.($r['archived_at']?'Restore':'Archive').'</button></form>';
            if(!(int)$r['enabled'])$html.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;margin:0"><input type="hidden" name="csrf_token" value="'.self::h(BxDolForm::getCsrfToken()).'"><input type="hidden" name="source_id" value="'.(int)$r['id'].'"><button type="submit" name="source_action" value="delete_preview" style="min-height:34px;padding:5px 14px;border:1px solid #ad6670;border-radius:7px;cursor:pointer">Delete</button></form>';
            $html.='</div></td></tr>';
        }
        if(!$visible)$html.='<tr><td colspan="6">No IPTV sources registered yet.</td></tr>';
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
