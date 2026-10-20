<?php
/** Original catalog parsing implementation, no third-party module code. */
class NwOnlineTvCatalog
{
    /** Parse bounded EXTINF/M3U text without fetching any remote URLs. */
    public static function parseM3u(string $playlist, int $maxEntries = 10000): array
    {
        if (strlen($playlist) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('Playlist exceeds 8 MiB limit');
        }
        $rows = preg_split('/\r\n|\n|\r/', $playlist);
        $entries = []; $pending = null; $seen = [];
        foreach ($rows as $line) {
            $line = trim($line);
            if (strncmp($line, '#EXTINF:', 8) === 0) {
                $comma = strrpos($line, ',');
                $title = $comma === false ? '' : trim(substr($line, $comma + 1));
                // Reject explicitly adult or closed entries before creating a general catalog candidate.
                $attributes = substr($line, 8, $comma === false ? strlen($line) : max(0, $comma - 8));
                $group = '';
                if (preg_match('/group-title="([^"]*)"/i', $attributes, $match)) $group = trim($match[1]);
                $blocked = preg_match('/(?:^|[^a-z0-9])(?:adult|xxx|porn|18\\+|nsfw|closed|offline)(?:$|[^a-z0-9])/i', $group . ' ' . $title);
                $pending = $title !== '' && !$blocked ? substr(strip_tags($title), 0, 255) : null;
                continue;
            }
            if ($line === '' || $line[0] === '#') continue;
            $url = filter_var($line, FILTER_VALIDATE_URL);
            $parts = $url ? parse_url($url) : false;
            if ($pending !== null && $parts && strtolower($parts['scheme'] ?? '') === 'https'
                && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])) {
                $host = strtolower($parts['host']);
                if ($host !== 'localhost' && !preg_match('/(^|\.)localhost$/', $host)
                    && !filter_var($host, FILTER_VALIDATE_IP)) {
                    $hash = hash('sha256', $line);
                    if (!isset($seen[$hash])) {
                        $entries[] = ['title' => $pending, 'stream_url' => $line, 'stream_hash' => $hash];
                        $seen[$hash] = true;
                        if (count($entries) >= $maxEntries) break;
                    }
                }
            }
            $pending = null;
        }
        return $entries;
    }
}
