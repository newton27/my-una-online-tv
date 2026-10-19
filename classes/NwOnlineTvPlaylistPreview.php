<?php
/**
 * Bounded, read-only M3U playlist preview. This class NEVER fetches network
 * resources and NEVER modifies the channel catalog.
 */
final class NwOnlineTvPlaylistPreview
{
    public static function parse(string $body, array $existingHashes = [], int $maxEntries = 100, int $page = 1, string $search = '', string $status = 'all', string $sort = 'original', bool $includePrivateUrls = false): array
    {
        if (strlen($body) > 2097152) throw new InvalidArgumentException('Playlist exceeds the preview size limit.');
        if (!preg_match('/^\\s*#EXTM3U(?:\\s|$)/i', substr($body, 0, 256))) throw new InvalidArgumentException('Not an extended M3U playlist.');
        $maxEntries = max(1, min(100, $maxEntries));
        $page = max(1, min(10000, $page)); $offset = ($page-1)*$maxEntries; $eligibleCount=0;
        $rows = []; $matches = []; $seen = []; $pending = ''; $skipped = 0; $duplicates = 0;
        $search=mb_substr(trim($search),0,120);
        if(!in_array($status,['all','candidate','duplicate'],true))$status='all';
        if(!in_array($sort,['original','name','name_desc','status'],true))$sort='original';
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
            $title=$pending!==''?$pending:'Unnamed channel';
            if(($search===''||mb_stripos($title,$search)!==false)&&($status==='all'||($status==='duplicate')===$dupe))
                $matches[]=['title'=>$title,'host'=>strtolower($url['host']),'duplicate'=>$dupe,'status'=>$dupe?'Duplicate':'Candidate','order'=>count($matches),'stream_url'=>$line];
            $eligibleCount++; $pending = '';
        }
        if($sort==='name'||$sort==='name_desc')usort($matches,static function($a,$b)use($sort){$n=strnatcasecmp($a['title'],$b['title']);return $n===0?$a['order']<=>$b['order']:($sort==='name_desc'?-1:1)*$n;});
        if($sort==='status')usort($matches,static fn($a,$b)=>strcmp($a['status'],$b['status'])?:($a['order']<=>$b['order']));
        $total=count($matches);$rows=array_slice($matches,$offset,$maxEntries);
        foreach($rows as &$row){unset($row['order']);if(!$includePrivateUrls)unset($row['stream_url']);}unset($row);
        return ['items'=>$rows, 'shown'=>count($rows), 'excluded'=>$skipped,
            'duplicates'=>$duplicates, 'total'=>$total, 'page'=>$page, 'pages'=>max(1,(int)ceil($total/$maxEntries)), 'truncated'=>$total>$maxEntries];
    }
}
