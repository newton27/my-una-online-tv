<?php
/**
 * XMLTV preview for My UNA World Online TV.
 * No database writes. No network fetching. Exact ID mappings only.
 *
 * Usage: php bin/xmltv-preview.php /home/duvallocals/guide.xml /home/duvallocals/channel-map.csv
 * channel-map.csv columns: xmltv_id,channel_id
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php xmltv-preview.php /absolute/guide.xml /absolute/channel-map.csv\n");
    exit(2);
}
function localInput(string $path, int $limit): string {
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, '/home/duvallocals/')
        || !is_file($real) || !is_readable($real) || filesize($real) > $limit) {
        throw new RuntimeException('An accessible, size-limited local file under /home/duvallocals/ is required.');
    }
    return $real;
}
function xmltvTime(string $raw): ?int {
    $raw = trim($raw);
    if (!preg_match('/^(\\d{14})\\s+([+-]\\d{4})$/', $raw, $parts)) return null;
    $date = DateTimeImmutable::createFromFormat('!YmdHis O', $parts[1] . ' ' . $parts[2]);
    return $date instanceof DateTimeImmutable ? $date->getTimestamp() : null;
}

try {
    $xml = localInput($argv[1], 5242880);
    $csv = localInput($argv[2], 131072);
    $handle = fopen($csv, 'rb');
    if ($handle === false || fgetcsv($handle) !== ['xmltv_id', 'channel_id']) {
        throw new RuntimeException('Mapping CSV requires xmltv_id,channel_id headers.');
    }
    $map = [];
    while (($record = fgetcsv($handle)) !== false) {
        if (count($record) !== 2 || count($map) >= 2000) throw new RuntimeException('Invalid mapping row or too many mappings.');
        [$xmltvId, $channelId] = $record;
        $channelId = filter_var($channelId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($xmltvId === '' || strlen($xmltvId) > 255 || !$channelId || isset($map[$xmltvId])) {
            throw new RuntimeException('Mappings must have unique, nonempty XMLTV IDs and valid UNA channel IDs.');
        }
        $map[$xmltvId] = $channelId;
    }
    fclose($handle);

    $reader = new XMLReader();
    if (!$reader->open($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new RuntimeException('XMLTV file cannot be opened.');
    }
    $reader->setParserProperty(XMLReader::LOADDTD, false);
    $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
    $reader->setParserProperty(XMLReader::VALIDATE, false);
    $stats = ['dry_run' => true, 'mapped_channels' => count($map), 'seen_programs' => 0,
        'eligible_programs' => 0, 'unmapped_programs' => 0, 'invalid_programs' => 0];
    $examples = [];
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'programme') continue;
        if (++$stats['seen_programs'] > 50000) throw new RuntimeException('XMLTV preview limited to 50,000 programs.');
        $channel = (string)$reader->getAttribute('channel');
        if (!isset($map[$channel])) { ++$stats['unmapped_programs']; continue; }
        $start = xmltvTime((string)$reader->getAttribute('start'));
        $stop = xmltvTime((string)$reader->getAttribute('stop'));
        if ($start === null || $stop === null || $start >= $stop || $stop - $start > 86400) {
            ++$stats['invalid_programs']; continue;
        }
        $node = $reader->expand();
        if (!$node) { ++$stats['invalid_programs']; continue; }
        $doc = new DOMDocument();
        $node = $doc->importNode($node, true);
        $doc->appendChild($node);
        $titles = $doc->getElementsByTagName('title');
        $title = trim((string)($titles->item(0)?->textContent ?? ''));
        if ($title === '' || mb_strlen($title) > 255) { ++$stats['invalid_programs']; continue; }
        ++$stats['eligible_programs'];
        if (count($examples) < 8) $examples[] = ['channel_id' => $map[$channel], 'title' => $title, 'starts_at' => $start, 'ends_at' => $stop];
    }
    $reader->close();
    echo json_encode($stats + ['examples' => $examples], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'XMLTV preview rejected: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
