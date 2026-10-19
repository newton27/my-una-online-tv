<?php
/**
 * Bounded, read-only M3U playlist preview. This class NEVER fetches network
 * resources and NEVER modifies the channel catalog.
 */
final class NwOnlineTvPlaylistPreview
{
    public static function parse(string $body, array $existingHashes = [], int $maxEntries = 100): array
    {
        if (strlen($body) > 2097152) throw new InvalidArgumentException('Playlist exceeds the preview size limit.');
        if (!preg_match('/^\\s*#EXTM3U(?:\\s|$)/i', substr($body, 0, 256))) throw new InvalidArgumentException('Not an extended M3U playlist.');
        $maxEntries = max(1, min(100, $maxEntries));
        $rows = []; $seen = []; $pending = ''; $skipped = 0; $duplicates = 0;
        foreach (preg_split('/\\r\\n|\\r|\\n/', $body) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXTINF:')) {
                $parts = explode(',', $line, 2);
                $pending = trim($parts[1] ?? '');
                $pending = preg_replace('/[\\x00-\\x1f\\x7f]/', '', $pending);
                $pending = mb_substr($pending, 0, 150);
                continue;
            }
            if ($line === '' || str_starts_with($line, '#')) continue;
            // Sensitive tokens, full URL, query parameters and fragments are never returned.
            $url = filter_var($line, FILTER_VALIDATE_URL) ? parse_url($line) : false;
            $safe = is_array($url) && strtolower($url['scheme'] ?? '') === 'https'
                && !empty($url['host']) && !isset($url['user']) && !isset($url['pass'])
                && !isset($url['fragment']) && (!isset($url['port']) || (int)$url['port'] === 443)
                && !filter_var($url['host'], FILTER_VALIDATE_IP)
                && strpos($url['host'], '.') !== false
                && preg_match('/\\.m3u8$/i', $url['path'] ?? '');
            if (!$safe) { $skipped++; $pending = ''; continue; }
            $hash = hash('sha256', $line);
            $dupe = isset($seen[$hash]) || isset($existingHashes[$hash]);
            if ($dupe) $duplicates++;
            $seen[$hash] = true;
            if (count($rows) < $maxEntries) {
                $rows[] = ['title'=>$pending !== '' ? $pending : 'Unnamed channel',
                    'host'=>strtolower($url['host']), 'duplicate'=>$dupe,
                    'status'=>$dupe ? 'Duplicate' : 'Candidate'];
            }
            $pending = '';
        }
        return ['items'=>$rows, 'shown'=>count($rows), 'excluded'=>$skipped,
            'duplicates'=>$duplicates, 'truncated'=>count($seen)>$maxEntries];
    }
}
