<?php
/**
 * Explicit CLI-only catalog importer. No scheduler or automatic publication.
 * Sources must be webmaster-owned, approved, enabled, general-audience and not archived.
 */
final class NwOnlineTvAutoCatalog
{
    public static function run(PDO $db, int $sourceId, bool $dryRun = true): array
    {
        if (PHP_SAPI !== 'cli' || $sourceId < 1 || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql')
            throw new RuntimeException('Authorized CLI and MariaDB required.');
        require_once __DIR__.'/NwOnlineTvPlaylistFetcher.php';
        require_once __DIR__.'/NwOnlineTvSyncPlanner.php';
        $lock=$db->query("SELECT GET_LOCK('nw_online_tv_catalog_import',0)")->fetchColumn();
        if ((int)$lock !== 1) throw new RuntimeException('Catalog worker already running.');
        try {
            $q=$db->prepare("SELECT s.playlist_url FROM nw_online_tv_sources s
                INNER JOIN nw_online_tv_source_classification c ON c.source_id=s.id
                LEFT JOIN nw_online_tv_source_archives a ON a.source_id=s.id
                WHERE s.id=? AND s.origin='webmaster' AND s.approval_status='approved'
                AND s.enabled=1 AND c.classification='general' AND a.source_id IS NULL");
            $q->execute([$sourceId]);
            $url=$q->fetchColumn();
            if (!$url) throw new RuntimeException('No eligible approved general-audience source.');
            require_once __DIR__.'/NwOnlineTvIptvOrg.php';
            $iptvOrg = hash_equals(NwOnlineTvIptvOrg::PLAYLIST, (string)$url);
            if ($iptvOrg) {
                $filtered = NwOnlineTvIptvOrg::filter(
                    NwOnlineTvPlaylistFetcher::fetch((string)$url, 8388608),
                    NwOnlineTvPlaylistFetcher::fetch(NwOnlineTvIptvOrg::METADATA, 12582912)
                );
                $plan=NwOnlineTvSyncPlanner::plan($filtered['playlist']);
            } else {
                $plan=NwOnlineTvSyncPlanner::plan(NwOnlineTvPlaylistFetcher::fetch((string)$url));
            }
            $result=['discovered'=>$plan['discovered'],'eligible'=>count($plan['eligible']),
                'excluded'=>$plan['excluded'],'added'=>0,'duplicates'=>0,'dry_run'=>$dryRun];
            if ($dryRun) return $result;
            $db->beginTransaction();
            try {
                $verify=$db->prepare("SELECT s.id FROM nw_online_tv_sources s
                    INNER JOIN nw_online_tv_source_classification c ON c.source_id=s.id
                    LEFT JOIN nw_online_tv_source_archives a ON a.source_id=s.id
                    WHERE s.id=? AND s.origin='webmaster' AND s.approval_status='approved'
                    AND s.enabled=1 AND c.classification='general' AND a.source_id IS NULL FOR UPDATE");
                $verify->execute([$sourceId]);
                if (!$verify->fetchColumn()) throw new RuntimeException('Source permissions changed.');
                $insert=$db->prepare("INSERT IGNORE INTO nw_online_tv_channels
                    (title,stream_url,stream_hash,source,country_code,language_code,
                     category,status,failure_count,review_required,created_at,updated_at)
                     VALUES (?,?,?,?,'','','general','pending',0,1,?,?)");
                foreach($plan['eligible'] as $entry) {
                    $now=time();
                    $insert->execute([$entry['title'],$entry['stream_url'],$entry['stream_hash'],'source:'.$sourceId,$now,$now]);
                    if($insert->rowCount()===1) $result['added']++;
                    else $result['duplicates']++;
                }
                $db->commit();
            } catch(Throwable $e) {
                if($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            return $result;
        } finally {
            $db->query("SELECT RELEASE_LOCK('nw_online_tv_catalog_import')");
        }
    }
}
