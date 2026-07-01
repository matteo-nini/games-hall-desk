<?php
/**
 * SimpleXLS — reads .xls (BIFF8/OLE2) and .xlsx files.
 * Returns rows as array<rowIndex, array<colIndex, string|float>>.
 * No external dependencies — pure PHP with ZipArchive + mb_string.
 */
class SimpleXLS {

    /** Auto-detect format and return rows (0-indexed row and column). */
    public static function read(string $path): array {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext === 'xlsx' ? self::readXlsx($path) : self::readXls($path);
    }

    // ── XLSX (ZIP + OpenXML) ────────────────────────────────────────────────

    private static function readXlsx(string $path): array {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive required');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Cannot open XLSX');

        // Shared strings
        $sst = [];
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw !== false) {
            $xml = @simplexml_load_string($raw);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $t = isset($si->t) ? (string)$si->t : implode('', array_map('strval', array_column(iterator_to_array($si->r ?? []), 't')));
                    $sst[] = $t;
                }
            }
        }

        // Find sheet1 XML
        $sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetRaw === false) {
            $relsRaw = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($relsRaw !== false) {
                $rx = @simplexml_load_string($relsRaw);
                if ($rx) {
                    foreach ($rx->Relationship as $rel) {
                        $t = (string)$rel['Target'];
                        if (str_contains($t, 'sheet')) {
                            $sheetRaw = $zip->getFromName('xl/' . ltrim($t, '/'));
                            if ($sheetRaw !== false) break;
                        }
                    }
                }
            }
        }
        $zip->close();

        if ($sheetRaw === false) throw new RuntimeException('Sheet not found in XLSX');
        $xml = @simplexml_load_string($sheetRaw);
        if (!$xml) throw new RuntimeException('Cannot parse XLSX sheet');

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $ri = (int)$row['r'] - 1;
            foreach ($row->c as $cell) {
                $ref  = (string)$cell['r'];
                $ci   = self::colIdx(preg_replace('/\d+/', '', $ref));
                $type = (string)($cell['t'] ?? '');
                $v    = (string)($cell->v ?? '');
                if ($type === 's')    { $rows[$ri][$ci] = $sst[(int)$v] ?? ''; }
                elseif ($type === 'str') { $rows[$ri][$ci] = $v; }
                elseif ($v !== '')    { $rows[$ri][$ci] = is_numeric($v) ? (float)$v : $v; }
            }
        }
        return $rows;
    }

    private static function colIdx(string $col): int {
        $idx = 0;
        foreach (str_split(strtoupper($col)) as $c) $idx = $idx * 26 + ord($c) - 64;
        return $idx - 1;
    }

    // ── XLS (BIFF8 over OLE2 compound document) ─────────────────────────────

    private static function readXls(string $path): array {
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) < 512) throw new RuntimeException('Cannot read XLS');
        if (substr($raw, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")
            throw new RuntimeException('Not a valid OLE2/XLS file');

        $secSize = 1 << self::u16($raw, 30); // usually 512
        $fatCnt  = self::u32($raw, 44);
        $dirSec  = self::u32($raw, 48);

        // Build FAT from the DIFAT array in the header (up to 109 entries)
        $fat = [];
        for ($i = 0; $i < 109 && $i < $fatCnt; $i++) {
            $sec = self::u32($raw, 76 + $i * 4);
            if ($sec >= 0xFFFFFFFE) break;
            $off = ($sec + 1) * $secSize;
            $n   = intdiv($secSize, 4);
            for ($j = 0; $j < $n; $j++) $fat[] = self::u32($raw, $off + $j * 4);
        }

        $sectors = static function (int $start) use ($raw, $secSize, $fat): string {
            $out = ''; $sec = $start; $seen = [];
            while ($sec < 0xFFFFFFFE) {
                if (isset($seen[$sec])) break;
                $seen[$sec] = true;
                $out .= substr($raw, ($sec + 1) * $secSize, $secSize);
                $sec = $fat[$sec] ?? 0xFFFFFFFE;
            }
            return $out;
        };

        // Parse directory entries (128 bytes each)
        $dir = [];
        $ds  = $sectors($dirSec);
        for ($i = 0, $n = intdiv(strlen($ds), 128); $i < $n; $i++) {
            $e    = substr($ds, $i * 128, 128);
            $nl   = self::u16($e, 64);
            $name = $nl > 2 ? mb_convert_encoding(substr($e, 0, $nl - 2), 'UTF-8', 'UTF-16LE') : '';
            $dir[] = ['n' => $name, 't' => ord($e[66]), 's' => self::u32($e, 116)];
        }

        // Find Workbook stream
        $wbStart = null;
        foreach ($dir as $e) {
            if ($e['t'] === 2 && in_array(strtolower($e['n']), ['workbook', 'book'], true)) {
                $wbStart = $e['s']; break;
            }
        }
        if ($wbStart === null) throw new RuntimeException('Workbook stream not found');

        $wb  = $sectors($wbStart);
        $len = strlen($wb);
        $pos = 0;
        $sst = []; $rows = [];

        while ($pos + 4 <= $len) {
            $type = self::u16($wb, $pos);
            $size = self::u16($wb, $pos + 2);
            $pos += 4;
            $size = min($size, $len - $pos);
            $data = substr($wb, $pos, $size);
            $pos += $size;

            switch ($type) {
                case 0x00FC: // SST — Shared String Table; accumulate CONTINUE records
                    $buf = $data;
                    while ($pos + 4 <= $len && self::u16($wb, $pos) === 0x003C) {
                        $cs   = self::u16($wb, $pos + 2); $pos += 4;
                        $buf .= substr($wb, $pos, $cs);   $pos += $cs;
                    }
                    $sst = self::parseSST($buf);
                    break;

                case 0x00FD: // LABELSST — cell referencing shared string
                    if ($size < 10) break;
                    $rows[self::u16($data, 0)][self::u16($data, 2)] = $sst[self::u32($data, 6)] ?? '';
                    break;

                case 0x0203: // NUMBER — IEEE 754 double
                    if ($size < 14) break;
                    $rows[self::u16($data, 0)][self::u16($data, 2)] = unpack('d', substr($data, 6, 8))[1];
                    break;

                case 0x027E: // RK — compressed number
                    if ($size < 10) break;
                    $rows[self::u16($data, 0)][self::u16($data, 2)] = self::rkVal(self::u32($data, 6));
                    break;

                case 0x00BE: // MULRK — multiple RK values in one row
                    if ($size < 6) break;
                    $r  = self::u16($data, 0);
                    $cf = self::u16($data, 2);
                    $nc = intdiv($size - 6, 6); // (size - row - colFirst - colLast) / (XF+RK)
                    for ($k = 0; $k < $nc; $k++) {
                        $rows[$r][$cf + $k] = self::rkVal(self::u32($data, 4 + $k * 6 + 2));
                    }
                    break;

                case 0x0204: // LABEL — inline Latin-1 string (BIFF5/7)
                    if ($size < 8) break;
                    $rows[self::u16($data, 0)][self::u16($data, 2)] = substr($data, 8, self::u16($data, 6));
                    break;
            }
        }
        return $rows;
    }

    /** Parse SST record payload (including appended CONTINUE bytes). */
    private static function parseSST(string $data): array {
        $sst = []; $len = strlen($data);
        if ($len < 8) return $sst;
        $unique = self::u32($data, 4);
        $pos    = 8;
        for ($i = 0; $i < $unique && $pos < $len; $i++) {
            if ($pos + 3 > $len) break;
            $chars = self::u16($data, $pos); $pos += 2;
            $flags = ord($data[$pos]);       $pos++;
            $uni   = (bool)($flags & 0x01);
            $rt    = ($flags & 0x08) ? self::u16($data, $pos) : 0; if ($flags & 0x08) $pos += 2;
            $as    = ($flags & 0x04) ? self::u32($data, $pos) : 0; if ($flags & 0x04) $pos += 4;
            $bytes = $uni ? $chars * 2 : $chars;
            if ($pos + $bytes > $len) break;
            $sst[] = $uni
                ? mb_convert_encoding(substr($data, $pos, $bytes), 'UTF-8', 'UTF-16LE')
                : substr($data, $pos, $bytes);
            $pos  += $bytes + $rt * 4 + $as;
        }
        return $sst;
    }

    /** Decode a 32-bit RK value to float. */
    private static function rkVal(int $rk): float {
        if ($rk & 0x02) {
            $v = $rk >> 2;
            if ($v >= 0x20000000) $v -= 0x40000000; // sign-extend 30 bits
        } else {
            $v = unpack('d', pack('VV', 0, $rk & 0xFFFFFFFC))[1]; // high 32 bits of double
        }
        return ($rk & 0x01) ? (float)$v / 100.0 : (float)$v;
    }

    private static function u16(string $s, int $o): int { return ord($s[$o]) | (ord($s[$o + 1]) << 8); }
    private static function u32(string $s, int $o): int { return unpack('V', substr($s, $o, 4))[1]; }
}
