<?php
/**
 * IPTV-org general-audience gate. Never treats unknown channel IDs as safe.
 * Metadata is required, with explicit is_nsfw=false and no closed/replacement.
 */
final class NwOnlineTvIptvOrg
{
    public const PLAYLIST='https://iptv-org.github.io/iptv/index.m3u';
    public const METADATA='https://iptv-org.github.io/api/channels.json';

    public static function filter(string $playlist, string $metadata): array
    {
        $items=json_decode($metadata,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($items) || count($items)<100) throw new RuntimeException('IPTV-org metadata unavailable.');
        $allowed=[];
        foreach($items as $entry) {
            if (!is_array($entry) || !isset($entry['id']) || !array_key_exists('is_nsfw',$entry)) continue;
            $categories=$entry['categories']??[];
            if ($entry['is_nsfw']!==false || !empty($entry['closed']) || !empty($entry['replaced_by'])
                || !is_array($categories) || in_array('adult',$categories,true)) continue;
            $allowed[(string)$entry['id']]=true;
        }
        $lines=preg_split('/\r\n|\n|\r/',$playlist);
        $out=['#EXTM3U'];$kept=0;$skipped=0;$pending=null;
        foreach($lines as $line) {
            $line=trim($line);
            if (str_starts_with($line,'#EXTINF:')) {
                $pending=null;
                if (preg_match('/\\btvg-id="([^"]+)"/',$line,$m)) {
                    $id=preg_replace('/@[^@]+$/','',$m[1]);
                    if (isset($allowed[$id]) && !preg_match('/\\b(?:http-user-agent|http-referrer|http-origin)=/i',$line)) $pending=$line;
                }
                continue;
            }
            if ($line==='' || $line[0]==='#') continue;
            if ($pending!==null) {
                $out[]=$pending;$out[]=$line;$kept++;
            } else $skipped++;
            $pending=null;
        }
        if ($kept===0) throw new RuntimeException('No eligible IPTV-org entries; refusing empty catalog.');
        return ['playlist'=>implode("\n",$out)."\n",'metadata_kept'=>$kept,'metadata_excluded'=>$skipped];
    }
}
