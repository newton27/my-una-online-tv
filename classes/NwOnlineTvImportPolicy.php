<?php
/** Independent catalog import policy. Pure validation; performs no network requests. */
final class NwOnlineTvImportPolicy
{
    public static function normalize(array $row): ?array
    {
        $title=trim(strip_tags((string)($row['title']??'')));
        $url=trim((string)($row['stream_url']??''));
        if($title==='' || strlen($title)>255 || strlen($url)>2048 || !filter_var($url,FILTER_VALIDATE_URL))return null;
        $p=parse_url($url);
        if(!is_array($p) || strtolower((string)($p['scheme']??''))!=='https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']))return null;
        $host=strtolower(rtrim($p['host'],'.'));
        if($host==='localhost' || str_ends_with($host,'.localhost') || filter_var($host,FILTER_VALIDATE_IP))return null;
        if(!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/',$host) || strpos($host,'.')===false)return null;
        if(isset($p['port']) && (int)$p['port']!==443)return null;
        $path=strtolower((string)($p['path']??''));
        if(!str_ends_with($path,'.m3u8'))return null;
        return ['title'=>$title,'stream_url'=>$url,'stream_hash'=>hash('sha256',$url)];
    }
}
