<?php
/**
 * Deliberate, bounded import policy. Import candidates are never accepted from
 * browser URLs; caller must re-fetch the approved source and reconstruct rows.
 * This class does not fetch playlists or publish channels.
 */
final class NwOnlineTvSelectedImport
{
    public static function normalizeSelection(array $indexes, array $eligible): array
    {
        if (!$indexes || count($indexes)>100) throw new InvalidArgumentException('Select 1 to 100 channels.');
        require_once __DIR__.'/NwOnlineTvImportPolicy.php';
        $selected=[]; $seen=[];
        foreach ($indexes as $index) {
            if (!is_scalar($index) || !ctype_digit((string)$index)) throw new InvalidArgumentException('Invalid selection.');
            $i=(int)$index;
            if ($i<0 || !array_key_exists($i,$eligible) || isset($seen[$i])) throw new InvalidArgumentException('Selection changed.');
            $seen[$i]=true;
            $candidate=NwOnlineTvImportPolicy::normalize($eligible[$i]);
            if ($candidate===null) throw new InvalidArgumentException('Channel is not eligible.');
            $selected[]=$candidate;
        }
        return $selected;
    }

    public static function savePending(PDO $db,array $selected,int $sourceId,string $classification,bool $authorized): array
    {
        if (!$authorized || $sourceId<1 || !in_array($classification,['adult','general'],true))
            throw new RuntimeException('Source classification and webmaster access required.');
        if (!$selected || count($selected)>100) throw new InvalidArgumentException('Invalid import batch.');
        if ($classification!=='adult')
            throw new RuntimeException('General-audience import requires an additional publication safety review.');
        require_once __DIR__.'/NwOnlineTvSourceClassification.php';
        if(NwOnlineTvSourceClassification::get($db,$sourceId)!==$classification)
            throw new RuntimeException('Stored source classification does not match this import.');
        $added=0;$existing=0;
        $db->beginTransaction();
        try {
            $q=$db->prepare("SELECT s.id FROM nw_online_tv_sources s LEFT JOIN nw_online_tv_source_archives a ON a.source_id=s.id WHERE s.id=? AND s.origin='webmaster' AND s.approval_status='approved' AND s.enabled=1 AND a.source_id IS NULL FOR UPDATE");
            $q->execute([$sourceId]);
            if (!$q->fetchColumn()) throw new RuntimeException('Source is no longer approved.');
            $insert=$db->prepare("INSERT IGNORE INTO nw_online_tv_channels (title,stream_url,stream_hash,source,country_code,language_code,category,status,failure_count,review_required,created_at,updated_at) VALUES (?,?,?,?,'','','adult','pending',0,1,?,?)");
            foreach($selected as $row){
                $clean=NwOnlineTvImportPolicy::normalize($row);
                if($clean===null)throw new InvalidArgumentException('Candidate changed.');
                $insert->execute([$clean['title'],$clean['stream_url'],$clean['stream_hash'],'source:'.$sourceId,time(),time()]);
                if($insert->rowCount()===1)$added++;else $existing++;
            }
            $db->commit();
            return ['added'=>$added,'duplicates'=>$existing];
        } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
