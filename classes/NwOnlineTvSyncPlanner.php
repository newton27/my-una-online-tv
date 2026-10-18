<?php
/** Converts pre-fetched playlist text into bounded, eligible rows. No remote fetch. */
require_once __DIR__.'/NwOnlineTvCatalog.php';
require_once __DIR__.'/NwOnlineTvImportPolicy.php';
final class NwOnlineTvSyncPlanner
{
    public static function plan(string $playlist,int $limit=10000): array
    {
        $limit=max(1,min(10000,$limit));
        $parsed=NwOnlineTvCatalog::parseM3u($playlist,$limit);
        $eligible=[];$excluded=0;
        foreach($parsed as $item) {
            $row=NwOnlineTvImportPolicy::normalize($item);
            if($row===null){$excluded++;continue;}
            $eligible[]=$row;
        }
        return ['discovered'=>count($parsed),'eligible'=>$eligible,'excluded'=>$excluded];
    }
}
