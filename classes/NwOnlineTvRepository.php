<?php
/**
 * My UNA World Online TV: independent data access implementation.
 * Requires a trusted PDO connection configured with exceptions enabled.
 * Not exposed to unauthenticated visitors.
 */
require_once __DIR__.'/NwOnlineTvImportPolicy.php';
final class NwOnlineTvRepository
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function summary(): array
    {
        $channels = (int)$this->db->query('SELECT COUNT(*) FROM nw_online_tv_channels')->fetchColumn();
        $review = (int)$this->db->query('SELECT COUNT(*) FROM nw_online_tv_channels WHERE review_required = 1')->fetchColumn();
        $reports = (int)$this->db->query('SELECT COUNT(*) FROM nw_online_tv_reports WHERE resolved_at IS NULL')->fetchColumn();
        $sync = $this->db->query("SELECT MAX(finished_at) FROM nw_online_tv_sync_runs WHERE result = 'completed'")->fetchColumn();
        return ['channels'=>$channels,'needs_review'=>$review,'reports_open'=>$reports,'last_sync'=>$sync ? gmdate('Y-m-d H:i',(int)$sync).' UTC':'Never'];
    }
    public function page(string $term='', string $status='', int $page=1, int $perPage=25): array
    {
        $term=substr(trim($term),0,120);
        $status=substr(trim($status),0,24);
        $perPage=max(1,min(100,$perPage));
        $filter=[];$bind=[];
        if ($term!=='') { $filter[]="(title LIKE :title OR category LIKE :category OR country_code LIKE :country)"; $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';$bind=[':title'=>$like,':category'=>$like,':country'=>$like]; }
        if ($status!=='') { $filter[]='status = :status'; $bind[':status']=$status; }
        $where=$filter ? ' WHERE '.implode(' AND ',$filter) : '';
        $where=str_replace(' LIKE :title',' LIKE :title ESCAPE \'!\'',$where);
        $where=str_replace(' LIKE :category',' LIKE :category ESCAPE \'!\'',$where);
        $where=str_replace(' LIKE :country',' LIKE :country ESCAPE \'!\'',$where);
        $countStmt=$this->db->prepare('SELECT COUNT(*) FROM nw_online_tv_channels'.$where);
        foreach($bind as $key=>$value)$countStmt->bindValue($key,$value,PDO::PARAM_STR);
        $countStmt->execute();$total=(int)$countStmt->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);
        $offset=($page-1)*$perPage;
        $stmt=$this->db->prepare('SELECT id,title,category,country_code,status,review_required FROM nw_online_tv_channels'.$where.' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        foreach($bind as $key=>$value)$stmt->bindValue($key,$value,PDO::PARAM_STR);
        $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
        $stmt->execute();
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    /** Explicitly invoked only by authenticated Studio sync service. Never deletes channels. */
    public function import(array $rows,string $source='manual'): array
    {
        if (!in_array($source,['manual','iptv-org','free-tv'],true)) throw new InvalidArgumentException('Unsupported catalog');
        $insertVerb=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt=$this->db->prepare($insertVerb.' INTO nw_online_tv_channels (title,stream_url,stream_hash,source,created_at,updated_at) VALUES (:title,:url,:hash,:source,:created,:updated)');
        $added=0;$existing=0;$now=time();
        foreach(array_slice($rows,0,10000) as $row) {
            if (!is_array($row))continue;
            $valid=NwOnlineTvImportPolicy::normalize($row);
            if($valid===null)continue;
            $stmt->execute([':title'=>$valid['title'],':url'=>$valid['stream_url'],':hash'=>$valid['stream_hash'],':source'=>$source,':created'=>$now,':updated'=>$now]);
            $stmt->rowCount()===1?$added++:$existing++;
        }
        return ['added'=>$added,'already_listed'=>$existing];
    }
}
