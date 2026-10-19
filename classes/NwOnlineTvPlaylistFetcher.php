<?php
/** Webmaster-initiated, read-only playlist fetch with DNS pinning and no redirects. */
final class NwOnlineTvPlaylistFetcher
{
    public static function fetch(string $url): string
    {
        if (!extension_loaded('curl')) throw new RuntimeException('Playlist retrieval unavailable.');
        if (strlen($url)>2048 || !filter_var($url,FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Invalid playlist address.');
        $p=parse_url($url);
        if (!is_array($p) || strtolower($p['scheme']??'')!=='https' || empty($p['host'])
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])
            || (isset($p['port']) && (int)$p['port']!==443)) throw new InvalidArgumentException('Unsupported playlist address.');
        $host=strtolower(rtrim($p['host'],'.'));
        if (!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/D',$host)
            || !str_contains($host,'.') || filter_var($host,FILTER_VALIDATE_IP)
            || $host==='localhost' || str_ends_with($host,'.localhost')) throw new InvalidArgumentException('Unsupported playlist host.');
        $ips=gethostbynamel($host);
        if (!$ips) throw new RuntimeException('Playlist host could not be resolved.');
        foreach($ips as $ip) {
            if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)
                || preg_match('/^(?:0|127|169\\.254|192\\.0\\.0|198\\.18|198\\.19)\\./',$ip))
                throw new RuntimeException('Playlist host address is not permitted.');
        }
        // TLS verification must be enabled and the validated address pinned against DNS rebinding.
        $handle=curl_init($url); $data=''; $tooLarge=false;
        curl_setopt_array($handle,[
            CURLOPT_RETURNTRANSFER=>false, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0,
            CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>10,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_PROXY=>'', CURLOPT_HTTPPROXYTUNNEL=>false,
            CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],
            CURLOPT_USERAGENT=>'MyUNAOnlineTV-PlaylistPreview/0.1',
            CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$data,&$tooLarge){
                if(strlen($data)+strlen($chunk)>2097152){$tooLarge=true;return 0;}
                $data.=$chunk;return strlen($chunk);
            }
        ]);
        try {
            $ok=curl_exec($handle); $code=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
        } finally { curl_close($handle); }
        if($tooLarge) throw new RuntimeException('Playlist exceeds preview size limit.');
        if($ok===false || $code!==200) throw new RuntimeException('Playlist unavailable or returned an unsupported response.');
        return $data;
    }
}
