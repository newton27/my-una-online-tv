<?php
/**
 * Independent read-only migration preview.
 * Inputs are caller-supplied legacy records; this class never opens legacy tables.
 */
require_once __DIR__.'/NwOnlineTvImportPolicy.php';
final class NwOnlineTvMigrationPreview
{
    public static function review(array $legacyRows, array $existingHashes=[], int $limit=10000): array
    {
        $limit=max(1,min(10000,$limit));$existing=array_fill_keys($existingHashes,true);
        $accepted=[];$duplicate=0;$invalid=0;$read=0;
        foreach($legacyRows as $row) {
            if($read++ >= $limit)break;
            $valid=is_array($row)?NwOnlineTvImportPolicy::normalize($row):null;
            if($valid===null){$invalid++;continue;}
            $hash=$valid['stream_hash'];
            if(isset($existing[$hash])){$duplicate++;continue;}
            $existing[$hash]=true;$accepted[]=$valid;
        }
        return ['scanned'=>min(count($legacyRows),$limit),'eligible'=>count($accepted),'duplicate'=>$duplicate,'rejected'=>$invalid,'preview'=>$accepted];
    }
}
