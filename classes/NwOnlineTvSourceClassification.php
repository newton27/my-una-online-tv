<?php
/** Source classification is private metadata; unknown sources cannot import. */
final class NwOnlineTvSourceClassification
{
    public static function get(PDO $db,int $sourceId): string
    {
        $s=$db->prepare('SELECT classification FROM nw_online_tv_source_classification WHERE source_id=?');
        $s->execute([$sourceId]);
        $value=$s->fetchColumn();
        return in_array($value,['adult','general'],true)?$value:'unclassified';
    }
    public static function set(PDO $db,int $sourceId,string $classification,bool $authorized): void
    {
        if(!$authorized||$sourceId<1||!in_array($classification,['adult','general','unclassified'],true))
            throw new InvalidArgumentException('Invalid classification.');
        $db->beginTransaction();
        try {
            $s=$db->prepare('SELECT id FROM nw_online_tv_sources WHERE id=? FOR UPDATE');
            $s->execute([$sourceId]);
            if(!$s->fetchColumn())throw new RuntimeException('Source not found.');
            if($classification==='unclassified'){
                $db->prepare('DELETE FROM nw_online_tv_source_classification WHERE source_id=?')->execute([$sourceId]);
            }else{
                $db->prepare('INSERT INTO nw_online_tv_source_classification (source_id,classification,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE classification=VALUES(classification),updated_at=VALUES(updated_at)')->execute([$sourceId,$classification,time()]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
