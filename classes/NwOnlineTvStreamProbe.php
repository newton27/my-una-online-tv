<?php
/**
 * Bounded, public HTTPS stream availability probe.
 * Does not follow redirects, download a full stream, flag or delete channels.
 * This is not a playback verifier. Requires PHP cURL.
 */
final class NwOnlineTvStreamProbe
{
    public static function check(string $url): array
    {
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            return ['result'=>'unknown','reason'=>'curl_unavailable'];
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            return ['result'=>'unknown','reason'=>'unsupported_url'];
        }
        $host = $parts['host'];
        if (!preg_match('/^[a-z0-9.-]+$/iD', $host) || strlen($host) > 253) {
            return ['result'=>'unknown','reason'=>'invalid_host'];
        }
        $ips = gethostbynamel($host);
        if (!$ips) return ['result'=>'unknown','reason'=>'dns_failed'];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return ['result'=>'unknown','reason'=>'non_public_destination'];
            }
        }
        $ip = $ips[0];
        $curl = curl_init($url);
        if (!$curl) return ['result'=>'unknown','reason'=>'curl_init_failed'];
        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => ['Range: bytes=0-2047','Accept: application/vnd.apple.mpegurl, application/x-mpegURL, */*'],
                CURLOPT_RESOLVE => [$host . ':443:' . $ip],
                CURLOPT_WRITEFUNCTION => static function ($handle, $data) {
                    return strlen($data) > 4096 ? 0 : strlen($data);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false) return ['result'=>'unknown','reason'=>'transport_error'];
            if ($status === 200 || $status === 206) return ['result'=>'reachable','reason'=>'http_success'];
            if ($status >= 500 && $status <= 599) return ['result'=>'failure','reason'=>'server_error'];
            if ($status === 404 || $status === 410) return ['result'=>'failure','reason'=>'not_found'];
            return ['result'=>'unknown','reason'=>'inconclusive_http_status'];
        } finally {
            curl_close($curl);
        }
    }
}
