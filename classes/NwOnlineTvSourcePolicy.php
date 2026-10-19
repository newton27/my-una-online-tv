<?php
/**
 * Curated IPTV feed registry. Does not fetch URLs or change channel records.
 * User submissions must remain pending until approved by a webmaster.
 */
final class NwOnlineTvSourcePolicy
{
    public static function validate(string $title, string $url): array
    {
        $title=trim(strip_tags($title));
        $url=trim($url);
        if($title==='' || mb_strlen($title)>120)throw new InvalidArgumentException('Source name must be 1–120 characters.');
        if(strlen($url)>2048 || !filter_var($url,FILTER_VALIDATE_URL))throw new InvalidArgumentException('Invalid source URL.');
        $p=parse_url($url);
        if(!is_array($p) || strtolower($p['scheme']??'')!=='https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || (isset($p['port']) && (int)$p['port']!==443))throw new InvalidArgumentException('Only public HTTPS sources on port 443 are accepted.');
        $host=strtolower(rtrim($p['host'],'.'));
        if(!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/D',$host) || strpos($host,'.')===false || filter_var($host,FILTER_VALIDATE_IP) || $host==='localhost' || str_ends_with($host,'.localhost'))throw new InvalidArgumentException('Source host is not permitted.');
        // Network DNS and redirect verification is required again at actual fetch time.
        return [$title,$url];
    }
    public static function save(PDO $db, int $id, string $title, string $url, bool $authorized): int
    {
        if(!$authorized)throw new RuntimeException('Webmaster access required.');
        if($id<0)throw new InvalidArgumentException('Invalid source identifier.');
        if($id>0 && trim($url)===''){
            $s=$db->prepare("SELECT playlist_url FROM nw_online_tv_sources WHERE id=? AND origin='webmaster'");
            $s->execute([$id]);
            $stored=$s->fetchColumn();
            if($stored===false)throw new RuntimeException('Source not found.');
            $url=(string)$stored;
        }
        [$title,$url]=self::validate($title,$url);
        $dup=$db->prepare('SELECT id FROM nw_online_tv_sources WHERE playlist_url=? AND id<>? LIMIT 1');
        $dup->execute([$url,$id]);
        if($dup->fetchColumn()!==false)throw new DomainException('Playlist already registered.');
        if($id===0){
            $s=$db->prepare("INSERT INTO nw_online_tv_sources (title,playlist_url,origin,approval_status,enabled,submitted_profile_id,created_at,updated_at) VALUES (?,?,'webmaster','approved',0,0,?,?)");
            $s->execute([$title,$url,time(),time()]);
            return (int)$db->lastInsertId();
        }
        $s=$db->prepare("UPDATE nw_online_tv_sources SET title=?,playlist_url=?,updated_at=? WHERE id=? AND origin='webmaster'");
        $s->execute([$title,$url,time(),$id]);
        if($s->rowCount()===0)throw new RuntimeException('Source not found or unchanged.');
        return $id;
    }
    public static function submit(PDO $db,int $profileId,string $title,string $url): int
    {
        if($profileId<1)throw new RuntimeException('Sign-in required.');
        [$title,$url]=self::validate($title,$url);
        $duplicate=$db->prepare('SELECT id FROM nw_online_tv_sources WHERE playlist_url=? LIMIT 1');
        $duplicate->execute([$url]);
        if($duplicate->fetchColumn()!==false)throw new DomainException('Playlist already registered.');
        $s=$db->prepare("INSERT INTO nw_online_tv_sources (title,playlist_url,origin,approval_status,enabled,submitted_profile_id,created_at,updated_at) VALUES (?,?,'member','pending',0,?,?,?)");
        $s->execute([$title,$url,$profileId,time(),time()]);
        return (int)$db->lastInsertId();
    }
    public static function moderate(PDO $db,int $id,string $action,bool $authorized): void
    {
        if(!$authorized)throw new RuntimeException('Webmaster access required.');
        if($id<1 || !in_array($action,['approve','reject','enable','disable'],true))throw new InvalidArgumentException('Invalid moderation action.');
        $db->beginTransaction();
        try{
            $s=$db->prepare('SELECT approval_status FROM nw_online_tv_sources WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Source not found.');
            $status=$row['approval_status'];
            if($action==='approve' && $status==='pending')$db->prepare("UPDATE nw_online_tv_sources SET approval_status='approved',enabled=0,updated_at=? WHERE id=?")->execute([time(),$id]);
            elseif($action==='reject' && $status==='pending')$db->prepare("UPDATE nw_online_tv_sources SET approval_status='rejected',enabled=0,updated_at=? WHERE id=?")->execute([time(),$id]);
            elseif($action==='enable' && $status==='approved'){
                $check=$db->prepare('SELECT source_id FROM nw_online_tv_source_archives WHERE source_id=?');
                $check->execute([$id]);
                if($check->fetchColumn()!==false)throw new RuntimeException('Restore source before enabling.');
                $db->prepare('UPDATE nw_online_tv_sources SET enabled=1,updated_at=? WHERE id=?')->execute([time(),$id]);
            }
            elseif($action==='disable')$db->prepare('UPDATE nw_online_tv_sources SET enabled=0,updated_at=? WHERE id=?')->execute([time(),$id]);
            else throw new RuntimeException('Invalid source transition.');
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    /** Archive flags kept separately from approval state; no channel changes. */
    public static function archive(PDO $db,int $id,bool $archived,bool $authorized): void
    {
        if(!$authorized||$id<1)throw new RuntimeException('Invalid archive request.');
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT id FROM nw_online_tv_sources WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if(!$q->fetchColumn())throw new RuntimeException('Source not found.');
            if($archived){
                $q=$db->prepare('INSERT INTO nw_online_tv_source_archives (source_id,archived_at) VALUES (?,?) ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at)');
                $q->execute([$id,time()]);
                $q=$db->prepare('UPDATE nw_online_tv_sources SET enabled=0,updated_at=? WHERE id=?');
                $q->execute([time(),$id]);
            }else{
                $q=$db->prepare('DELETE FROM nw_online_tv_source_archives WHERE source_id=?');
                $q->execute([$id]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function delete(PDO $db,int $id,string $expectedTitle,bool $authorized): void
    {
        if(!$authorized||$id<1)throw new RuntimeException('Unauthorized source deletion.');
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT title,enabled FROM nw_online_tv_sources WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||!hash_equals((string)$row['title'],$expectedTitle)||((int)$row['enabled'])!==0)
                throw new RuntimeException('Source changed or must be disabled before deletion.');
            $db->prepare('DELETE FROM nw_online_tv_source_archives WHERE source_id=?')->execute([$id]);
            $q=$db->prepare('DELETE FROM nw_online_tv_sources WHERE id=?');
            $q->execute([$id]);
            if($q->rowCount()!==1)throw new RuntimeException('Source deletion was not completed.');
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

}
