<?php
/**
 * Read-only UNA 15 Studio overview. No catalog mutations or legacy module access.
 */
defined('BX_DOL') or die('Access denied');
class NwOnlineTvStudioPage extends BxTemplStudioModule
{
    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);
        $this->sPageDefault = 'overview';
        if ($sPage === '') $this->sPage = 'overview';
        $this->aMenuItems = [
            'overview' => ['name' => 'overview', 'icon' => 'home', 'title' => 'Overview'],
            'channels' => ['name' => 'channels', 'icon' => 'tv', 'title' => 'Channels'],
            'sync' => ['name' => 'sync', 'icon' => 'database', 'title' => 'Sync'],
            'reports' => ['name' => 'reports', 'icon' => 'flag', 'title' => 'Reports'],
            'settings' => ['name' => 'settings', 'icon' => 'cogs', 'title' => 'Settings'],
        ];
    }

    public function getPageCode($sPage = '', $bWrap = true)
    {
        require_once __DIR__ . '/NwOnlineTvDashboard.php';
        $metrics = ['channels' => 0, 'needs_review' => 0, 'reports_open' => 0, 'last_sync' => 'Never'];
        $error = '';
        try {
            $db = new mysqli(BX_DATABASE_HOST, BX_DATABASE_USER, BX_DATABASE_PASS, BX_DATABASE_NAME);
            if ($db->connect_errno) {
                throw new RuntimeException('Database unavailable');
            }
            $queries = [
                'channels' => 'SELECT COUNT(*) FROM nw_online_tv_channels',
                'needs_review' => 'SELECT COUNT(*) FROM nw_online_tv_channels WHERE review_required = 1',
                'reports_open' => 'SELECT COUNT(*) FROM nw_online_tv_reports WHERE resolved_at IS NULL',
            ];
            foreach ($queries as $key => $sql) {
                $result = $db->query($sql);
                if ($result === false) throw new RuntimeException('Metrics query failed');
                $metrics[$key] = (int)$result->fetch_row()[0];
            }
            $result = $db->query("SELECT MAX(finished_at) FROM nw_online_tv_sync_runs WHERE result = 'completed' AND finished_at IS NOT NULL");
            if ($result === false) throw new RuntimeException('Sync query failed');
            $timestamp = (int)$result->fetch_row()[0];
            if ($timestamp > 0) $metrics['last_sync'] = gmdate('Y-m-d H:i', $timestamp) . ' UTC';
            $db->close();
        } catch (Throwable $e) {
            error_log('nw_online_tv Studio metrics: ' . $e->getMessage());
            $error = '<p class="nwotv-alert" role="alert">Dashboard statistics are temporarily unavailable. Check the PHP error log.</p>';
        }
        $cssPath = dirname(__DIR__) . '/template/css/dashboard.css';
        $css = is_readable($cssPath) ? '<style>' . file_get_contents($cssPath) . '</style>' : '';
        $page = (string)($sPage !== '' ? $sPage : ($_GET['page'] ?? 'overview'));
        if (!in_array($page, ['overview', 'channels', 'sync', 'reports', 'settings'], true)) $page = 'overview';
        if ($page === 'channels') {
            try {
                require_once __DIR__ . '/NwOnlineTvChannelsStudio.php';
                $pdo = new PDO(
                    'mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
                    BX_DATABASE_USER,
                    BX_DATABASE_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $notice = '';
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                    try {
                        require_once __DIR__ . '/NwOnlineTvBulkPolicy.php';
                        $authorized = function_exists('isAdmin') && isAdmin();
                        $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
                            && BxDolForm::isCsrfTokenValid($_POST['csrf_token']);
                        $action = isset($_POST['bulk_action']) && is_string($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
                        $ids = isset($_POST['channel_ids']) && is_array($_POST['channel_ids']) ? $_POST['channel_ids'] : [];
                        if ($action === 'preview_delete') {
                            $preview = NwOnlineTvBulkPolicy::previewDeletion($pdo, $ids, $authorized, $csrf);
                            $notice = '<section class="nwotv-preview" role="status"><h3>Deletion preview — no changes made</h3><p><strong>' . (int)$preview['count'] . '</strong> selected channels from this page would be affected.</p><ol>';
                            foreach ($preview['titles'] as $title) {
                                $notice .= '<li>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                            }
                            $notice .= '</ol><p>Confirm only these explicit channel IDs. This permanently removes their associated reports too.</p>';
                            $notice .= '<form method="post" action="module.php?name=nw_online_tv&amp;page=channels">';
                            $notice .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)BxDolForm::getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
                            foreach ($preview['ids'] as $id) $notice .= '<input type="hidden" name="channel_ids[]" value="' . (int)$id . '">';
                            $notice .= '<p class="nwotv-confirm-label">You are about to permanently delete <strong>' . (int)$preview['count'] . '</strong> selected channel(s). Verify the names above, then confirm once. No typing required.</p>';
                            $notice .= '<div class="nwotv-confirm-actions"><button class="nwotv-confirm-danger" type="submit" name="bulk_action" value="delete_confirm">Permanently delete ' . (int)$preview['count'] . ' selected channel(s)</button><a class="nwotv-confirm-cancel" href="module.php?name=nw_online_tv&amp;page=channels">Cancel</a></div></form></section>';
                        } elseif ($action === 'delete_confirm') {
                            // The second, CSRF-protected confirmation click is the deliberate authorization.
                            // Retain the policy's exact-count check while removing repetitive manual typing.
                            $confirmedIds = NwOnlineTvBulkPolicy::channelIds($ids);
                            $affected = NwOnlineTvBulkPolicy::deleteSelected($pdo, $confirmedIds, $authorized, $csrf, 'DELETE ' . count($confirmedIds));
                            $notice = '<p class="nwotv-success" role="status">' . (int)$affected . ' channels permanently deleted.</p>';
                        } else {
                            $affected = NwOnlineTvBulkPolicy::applyReview($pdo, $action, $ids, $authorized, $csrf);
                            $notice = '<p class="nwotv-success" role="status">' . (int)$affected . ' channel records updated.</p>';
                        }
                    } catch (Throwable $e) {
                        // Show only a safe failure category. Never expose tokens, queries or exception text.
                        $category = !$authorized ? 'ADMIN' : (!$csrf ? 'CSRF' : ($e instanceof PDOException ? 'DATABASE' : 'VALIDATION'));
                        if ($category === 'VALIDATION') {
                            $message = $e->getMessage();
                            if ($message === 'Deletion confirmation did not match the selection count.') $category = 'CONFIRMATION_MISMATCH';
                            elseif (strpos($message, 'Selection changed') === 0) $category = 'SELECTION_CHANGED';
                            elseif (strpos($message, 'Select between 1 and 100') === 0 || strpos($message, 'Invalid channel identifier') === 0 || strpos($message, 'Duplicate channel identifiers') === 0) $category = 'INVALID_SELECTION';
                            elseif ($message === 'Unsupported deletion database driver.') $category = 'UNSUPPORTED_DRIVER';
                            elseif ($message === 'Deletion count mismatch. Transaction rolled back.') $category = 'COUNT_MISMATCH';
                        }
                        error_log('nw_online_tv bulk review [' . $category . ']: ' . get_class($e));
                        $notice = '<p class="nwotv-alert" role="alert">Action rejected (' . $category . '). No changes confirmed. Review the selection or contact the administrator.</p>';
                    }
                }
                return $css . $notice . NwOnlineTvChannelsStudio::render($pdo);
            } catch (Throwable $e) {
                error_log('nw_online_tv Studio channels: ' . $e->getMessage());
                return $css . '<section class="nwotv-dashboard"><p class="nwotv-alert" role="alert">Channel list is temporarily unavailable. Check PHP error log.</p></section>';
            }
        }
        if ($page === 'sync') {
            try {
                require_once __DIR__ . '/NwOnlineTvSyncStudio.php';
                $pdo = new PDO(
                    'mysql:host=' . BX_DATABASE_HOST . ';dbname=' . BX_DATABASE_NAME . ';charset=utf8mb4',
                    BX_DATABASE_USER, BX_DATABASE_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $notice='';
                if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
                    try {
                        require_once __DIR__ . '/NwOnlineTvSourcePolicy.php';
                        $auth=function_exists('isAdmin') && isAdmin();
                        $csrf=isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) && BxDolForm::isCsrfTokenValid($_POST['csrf_token']);
                        if (!$auth || !$csrf)throw new RuntimeException('Unauthorized source action.');
                        $action=(string)($_POST['source_action']??'');
                        $id=filter_var($_POST['source_id']??0,FILTER_VALIDATE_INT);
                        if($id===false || $id<0)throw new InvalidArgumentException('Invalid source ID.');
                        if($action==='classify'){
                            require_once __DIR__.'/NwOnlineTvSourceClassification.php';
                            $classification=isset($_POST['source_classification']) && is_string($_POST['source_classification']) ? $_POST['source_classification'] : '';
                            NwOnlineTvSourceClassification::set($pdo,$id,$classification,$auth);
                            $notice='<p class="nwotv-success" role="status">Source audience saved. No channels imported or published.</p>';
                        } elseif($action==='delete_preview'){
                            $q=$pdo->prepare('SELECT title,enabled FROM nw_online_tv_sources WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
                            if(!$row||(int)$row['enabled']!==0)throw new RuntimeException('Disable source before deleting.');
                            $notice='<section class="nwotv-preview"><h3>Confirm permanent source deletion</h3><p>Delete source <strong>'.htmlspecialchars($row['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</strong>? Its saved playlist address will be removed. Existing channels and reports will stay unchanged.</p><form method="post" action="module.php?name=nw_online_tv&amp;page=sync"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><input type="hidden" name="expected_title" value="'.htmlspecialchars($row['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"><button type="submit" name="source_action" value="delete_confirm" style="padding:10px 16px;border:1px solid #b65c66;border-radius:8px">Delete Source Permanently</button> <a href="module.php?name=nw_online_tv&amp;page=sync">Cancel</a></form></section>';
                        } elseif($action==='delete_confirm'){
                            NwOnlineTvSourcePolicy::delete($pdo,$id,(string)($_POST['expected_title']??''),$auth);
                            $notice='<p class="nwotv-success" role="status">Source deleted permanently. Existing channels and reports preserved.</p>';
                        } elseif($action==='archive' || $action==='restore'){
                            NwOnlineTvSourcePolicy::archive($pdo,$id,$action==='archive',$auth);
                            $notice='<p class="nwotv-success" role="status">IPTV source '.($action==='archive'?'archived and disabled':'restored (still disabled)').'. Existing channels were not changed.</p>';
                        } elseif($action==='preview_source' || $action==='review_selection' || $action==='import_confirm'){
                            if($id<1)throw new InvalidArgumentException('Invalid source.');
                            $q=$pdo->prepare("SELECT s.playlist_url,s.origin FROM nw_online_tv_sources s LEFT JOIN nw_online_tv_source_archives a ON a.source_id=s.id WHERE s.id=? AND s.approval_status='approved' AND s.enabled=1 AND a.source_id IS NULL");
                            $q->execute([$id]); $src=$q->fetch(PDO::FETCH_ASSOC);
                            if(!$src)throw new RuntimeException('Source unavailable.');
                            // Member subscription URLs are not fetched or shared through a public catalog.
                            if($src['origin']!=='webmaster')throw new RuntimeException('Member feeds require separate private-access review.');
                            require_once __DIR__.'/NwOnlineTvPlaylistFetcher.php';
                            require_once __DIR__.'/NwOnlineTvPlaylistPreview.php';
                            $body=NwOnlineTvPlaylistFetcher::fetch((string)$src['playlist_url']);
                            $hashes=[];foreach($pdo->query('SELECT stream_hash FROM nw_online_tv_channels') as $entry)$hashes[$entry['stream_hash']]=true;
                            $pageNumber=filter_var($_POST['preview_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]])?:1;
                            $search=mb_substr(trim((string)($_POST['filter_search']??'')),0,120);
                            $filterStatus=(string)($_POST['filter_status']??'all');
                            $filterSort=(string)($_POST['filter_sort']??'original');
                            $preview=NwOnlineTvPlaylistPreview::parse($body,$hashes,100,$pageNumber,$search,$filterStatus,$filterSort,$action==='import_confirm');
                            if($action==='import_confirm'){
                                require_once __DIR__.'/NwOnlineTvSelectedImport.php';
                                require_once __DIR__.'/NwOnlineTvSourceClassification.php';
                                $raw=isset($_POST['selection'])&&is_array($_POST['selection'])?$_POST['selection']:[];
                                if(!$raw||count($raw)>100)throw new RuntimeException('Invalid import selection.');
                                $indexes=[];
                                foreach($raw as $v){if(!is_scalar($v)||!ctype_digit((string)$v)||!array_key_exists((int)$v,$preview['items']))throw new RuntimeException('Invalid channel index.');$indexes[]=(int)$v;}
                                if(count($indexes)!==count(array_unique($indexes)))throw new RuntimeException('Repeated channel index.');
                                sort($indexes,SORT_NUMERIC);
                                $stamp=filter_var($_POST['import_timestamp']??null,FILTER_VALIDATE_INT);
                                if(!$stamp||$stamp>time()+30||$stamp<time()-600)throw new RuntimeException('Import confirmation expired. Review selection again.');
                                $payload=json_encode([$id,$pageNumber,$search,$filterStatus,$filterSort,$indexes,$stamp,hash('sha256',$body)],JSON_UNESCAPED_SLASHES);
                                $signature=hash_hmac('sha256',$payload,(string)BX_DATABASE_PASS);
                                if(!isset($_POST['import_signature'])||!is_string($_POST['import_signature'])||!hash_equals($signature,$_POST['import_signature']))throw new RuntimeException('Selection changed. Review channels again.');
                                if(NwOnlineTvSourceClassification::get($pdo,$id)!=='adult')throw new RuntimeException('Only classified adult sources can enter the protected pending catalog.');
                                $selected=NwOnlineTvSelectedImport::normalizeSelection(array_map('strval',$indexes),$preview['items']);
                                $result=NwOnlineTvSelectedImport::savePending($pdo,$selected,$id,'adult',$auth);
                                $notice='<section class="nwotv-preview" role="status"><h3>Import completed — pending review</h3><p>'.(int)$result['added'].' channel(s) added as pending and hidden from the public directory. '.(int)$result['duplicates'].' already listed.</p><p>Channels have not been published.</p><a href="module.php?name=nw_online_tv&amp;page=sync">Return to Sync</a></section>';
                                return $css.$notice.NwOnlineTvSyncStudio::render($pdo);
                            }
                            $chosen=[];
                            if($action==='review_selection'){
                                $raw=isset($_POST['selection']) && is_array($_POST['selection']) ? $_POST['selection'] : [];
                                if(count($raw)<1 || count($raw)>100)throw new InvalidArgumentException('Select 1-100 channels.');
                                foreach($raw as $index){
                                    if(!is_scalar($index) || !ctype_digit((string)$index) || (int)$index>=count($preview['items']))throw new InvalidArgumentException('Invalid selection.');
                                    $chosen[(int)$index]=true;
                                }
                            }
                            if($action==='review_selection'){
                                $reviewed=[];
                                foreach(array_keys($chosen) as $selectedIndex){
                                    $entry=$preview['items'][$selectedIndex];
                                    if($entry['duplicate'])continue;
                                    $reviewed[]=$entry;
                                }
                                $reviewNotice='<section class="nwotv-preview" role="status"><h3>Review selected channels — import not yet enabled</h3><p><strong>'.count($reviewed).'</strong> eligible channel(s) selected from this page. '.(count($chosen)-count($reviewed)).' duplicates excluded.</p><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Channel</th><th>Host</th><th>Status</th></tr></thead><tbody>';
                                foreach($reviewed as $entry)$reviewNotice.='<tr><td>'.htmlspecialchars($entry['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>'.htmlspecialchars($entry['host'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>Ready for further review</td></tr>';
                                $reviewNotice.='</tbody></table></div><p>Adult imports are saved as pending and hidden from public listings. General-audience and member sources cannot be imported here.</p></section>';
                            }
                            $notice='<section class="nwotv-preview"><h3>'.($action==='review_selection'?'Selection review — no import performed':'Preview Channels — no import performed').'</h3><p>Showing '.(int)$preview['shown'].' of '.(int)$preview['total'].' channels (page '.(int)$preview['page'].' of '.(int)$preview['pages'].').</p>';
                            $notice.='<style>.nwotv-preview{width:100%;max-width:none;box-sizing:border-box}.nwotv-preview .nwotv-filterbar{display:grid!important;grid-template-columns:minmax(230px,2fr) minmax(150px,1fr) minmax(150px,1fr) auto;gap:14px;align-items:end;width:100%;box-sizing:border-box;padding:18px;margin:18px 0;background:rgba(120,140,165,.09);border:1px solid rgba(120,140,165,.24);border-radius:12px}.nwotv-preview .nwotv-filterbar label{display:flex!important;flex-direction:column!important;align-items:stretch!important;gap:7px;width:auto!important;margin:0!important;font-weight:600;text-align:left!important}.nwotv-preview .nwotv-filterbar input,.nwotv-preview .nwotv-filterbar select{width:100%!important;min-width:0;min-height:42px;padding:9px 12px;border:1px solid #8493a6;border-radius:8px;background:var(--bx-color-box,white);color:inherit;box-sizing:border-box}.nwotv-preview .nwotv-action-btn{display:inline-flex!important;align-items:center;justify-content:center;min-height:42px;padding:9px 18px!important;border:1px solid #62758b!important;border-radius:8px!important;cursor:pointer!important;font-weight:600!important;text-decoration:none!important;white-space:nowrap}.nwotv-preview .nwotv-table-wrap{width:100%!important;max-width:none!important;overflow-x:auto}.nwotv-preview .nwotv-table{width:100%!important;min-width:600px}.nwotv-preview .nwotv-action-row{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:18px 0}@media(max-width:850px){.nwotv-preview .nwotv-filterbar{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.nwotv-preview .nwotv-filterbar{grid-template-columns:1fr}}</style>'; 
                            $notice.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" class="nwotv-filterbar"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><label>Search channels<input type="search" name="filter_search" maxlength="120" value="'.htmlspecialchars($search,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" placeholder="Channel name"></label><label>Status<select name="filter_status">';
                            foreach(['all'=>'All channels','candidate'=>'Candidates','duplicate'=>'Duplicates'] as $value=>$label)$notice.='<option value="'.$value.'"'.($filterStatus===$value?' selected':'').'>'.$label.'</option>';
                            $notice.='</select></label><label>Sort<select name="filter_sort">';
                            foreach(['original'=>'Playlist order','name'=>'Name A–Z','name_desc'=>'Name Z–A','status'=>'Status'] as $value=>$label)$notice.='<option value="'.$value.'"'.($filterSort===$value?' selected':'').'>'.$label.'</option>';
                            $notice.='</select></label><button class="nwotv-action-btn" type="submit" name="source_action" value="preview_source">Apply Filters</button></form>';
                            $notice.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><input type="hidden" name="preview_page" value="'.(int)$pageNumber.'">';
                            $notice.='<div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th><input type="checkbox" id="nwotv-select-page" aria-label="Select all on page"></th><th>Channel</th><th>Host</th><th>Status</th></tr></thead><tbody>';
                            foreach($preview['items'] as $index=>$item){
                                $notice.='<tr><td><input type="checkbox" class="nwotv-select-channel" name="selection[]" value="'.(int)$index.'"'.(isset($chosen[$index])?' checked':'').'></td><td>'.htmlspecialchars($item['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>'.htmlspecialchars($item['host'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>'.htmlspecialchars($item['status'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td></tr>';
                            }
                            $notice.='</tbody></table></div><p><button type="submit" class="nwotv-action-btn" name="source_action" value="review_selection">Review Selected Channels</button> <strong>No import will be performed.</strong></p></form>';
                            $notice.='<script>(function(){var all=document.getElementById("nwotv-select-page");if(!all)return;all.addEventListener("change",function(){document.querySelectorAll(".nwotv-select-channel").forEach(function(c){c.checked=all.checked;});});})();</script>';
                            if($preview['pages']>1){
                                $notice.='<div style="display:flex;gap:10px;align-items:center;margin-top:15px">';
                                foreach([max(1,$pageNumber-1)=>'Previous',min($preview['pages'],$pageNumber+1)=>'Next'] as $number=>$label){
                                    if($number===$pageNumber)continue;
                                    $notice.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><input type="hidden" name="preview_page" value="'.(int)$number.'"><button type="submit" class="nwotv-action-btn" name="source_action" value="preview_source">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</button></form>';
                                }
                                $notice.='</div>';
                            }
                            $notice.='</section>';
                            if(isset($reviewNotice)){
                                require_once __DIR__.'/NwOnlineTvSourceClassification.php';
                                $canImport=count($reviewed)>0 && NwOnlineTvSourceClassification::get($pdo,$id)==='adult';
                                $stamp=time();$indexes=[];$selectionInputs='';
                                foreach(array_keys($chosen) as $index){if(!$preview['items'][$index]['duplicate']){$indexes[]=(int)$index;$selectionInputs.='<input type="hidden" name="selection[]" value="'.(int)$index.'">';}}
                                sort($indexes,SORT_NUMERIC);
                                $payload=json_encode([$id,$pageNumber,$search,$filterStatus,$filterSort,$indexes,$stamp,hash('sha256',$body)],JSON_UNESCAPED_SLASHES);
                                $signature=hash_hmac('sha256',$payload,(string)BX_DATABASE_PASS);
                                $reviewNotice=substr($reviewNotice,0,-strlen('</section>'));
                                $reviewNotice.='<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="margin-top:18px;display:inline-flex;gap:12px;align-items:center"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><input type="hidden" name="preview_page" value="'.(int)$pageNumber.'"><input type="hidden" name="filter_search" value="'.htmlspecialchars($search,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"><input type="hidden" name="filter_status" value="'.htmlspecialchars($filterStatus,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="filter_sort" value="'.htmlspecialchars($filterSort,ENT_QUOTES,'UTF-8').'"><button type="submit" name="source_action" value="preview_source" style="padding:11px 18px;border:1px solid #687991;border-radius:8px;cursor:pointer;font-weight:600">Back to Selection</button></form>'.($canImport?'<form method="post" action="module.php?name=nw_online_tv&amp;page=sync" style="display:inline-flex;align-items:center;gap:8px"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(BxDolForm::getCsrfToken(),ENT_QUOTES,'UTF-8').'"><input type="hidden" name="source_id" value="'.(int)$id.'"><input type="hidden" name="preview_page" value="'.(int)$pageNumber.'"><input type="hidden" name="filter_search" value="'.htmlspecialchars($search,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"><input type="hidden" name="filter_status" value="'.htmlspecialchars($filterStatus,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="filter_sort" value="'.htmlspecialchars($filterSort,ENT_QUOTES,'UTF-8').'">'.$selectionInputs.'<input type="hidden" name="import_timestamp" value="'.$stamp.'"><input type="hidden" name="import_signature" value="'.$signature.'"><button type="submit" name="source_action" value="import_confirm" style="padding:11px 18px;border:1px solid #68809b;border-radius:8px;font-weight:600;cursor:pointer" onclick="return confirm(\'Import these adult channels as pending, hidden listings?\')">Import Selected as Pending</button></form>':'<button type="button" disabled style="padding:11px 18px;border-radius:8px;opacity:.55">Import unavailable for this source</button>').'</section>';
                                $notice=$reviewNotice;
                            }
                        } elseif($action==='preview_text'){
                            require_once __DIR__ . '/NwOnlineTvPlaylistPreview.php';
                            $text=isset($_POST['playlist_text']) && is_string($_POST['playlist_text']) ? $_POST['playlist_text'] : '';
                            $hashes=[];
                            foreach($pdo->query('SELECT stream_hash FROM nw_online_tv_channels') as $entry) $hashes[$entry['stream_hash']]=true;
                            $preview=NwOnlineTvPlaylistPreview::parse($text,$hashes,100);
                            $notice='<section class="nwotv-preview"><h3>Playlist preview — no channels imported</h3><p>Showing '.(int)$preview['shown'].' entries. Duplicates: '.(int)$preview['duplicates'].'. Excluded: '.(int)$preview['excluded'].'.</p><div class="nwotv-table-wrap"><table class="nwotv-table"><thead><tr><th>Channel name</th><th>Host</th><th>Status</th></tr></thead><tbody>';
                            foreach($preview['items'] as $entry){$notice.='<tr><td>'.htmlspecialchars($entry['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>'.htmlspecialchars($entry['host'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td><td>'.htmlspecialchars($entry['status'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td></tr>';}
                            $notice.='</tbody></table></div><p>Preview only. Full stream URLs and query tokens are not displayed. No import action is available.</p></section>';
                        } elseif($action==='save')NwOnlineTvSourcePolicy::save($pdo,$id,(string)($_POST['source_title']??''),(string)($_POST['source_url']??''),$auth);
                        else NwOnlineTvSourcePolicy::moderate($pdo,$id,$action,$auth);
                        if(!in_array($action,['preview_text','preview_source','review_selection','import_confirm','archive','restore','delete_preview','delete_confirm','classify'],true))$notice='<p class="nwotv-success" role="status">IPTV source updated. No import started.</p>';
                    } catch(Throwable $e){
                        error_log('nw_online_tv source action: '.get_class($e));
                        $notice='<p class="nwotv-alert" role="alert">'.($e instanceof DomainException?'This playlist URL is already registered. The existing source was not changed.':'Source change rejected. Check source status or your administrator session.').'</p>';
                    }
                }
                return $css . $notice . NwOnlineTvSyncStudio::render($pdo);
            } catch (Throwable $e) {
                error_log('nw_online_tv Studio sync: ' . $e->getMessage());
                return $css . '<section class="nwotv-dashboard"><p class="nwotv-alert" role="alert">Sync history is temporarily unavailable. Check PHP error log.</p></section>';
            }
        }
        if ($page === 'overview') {
            return $css . $error . NwOnlineTvDashboard::render($metrics);
        }
        $titles = ['channels' => 'Channels', 'sync' => 'Channel synchronization', 'reports' => 'Channel reports', 'settings' => 'Settings'];
        $name = htmlspecialchars($titles[$page], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $css . '<section class="nwotv-dashboard"><header class="nwotv-heading"><h2>' . $name . '</h2><p>Management controls are under development. No changes can be made on this page yet.</p></header></section>';

    }
}
