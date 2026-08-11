<?php

namespace App\Support;

use ZipArchive;

class Xlsx
{
    public static function export(array $headers, array $rows): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';
        $xml .= '<row r="1">';
        foreach ($headers as $h) {
            $xml .= "<c t=\"inlineStr\"><is><t>{$esc($h)}</t></is></c>";
        }
        $xml .= '</row>';
        foreach ($rows as $i => $row) {
            $xml .= '<row r="' . ($i + 2) . '">';
            foreach ($row as $v) {
                if (is_numeric($v)) {
                    $xml .= "<c><v>{$esc($v)}</v></c>";
                } else {
                    $xml .= "<c t=\"inlineStr\"><is><t>{$esc($v)}</t></is></c>";
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Inventory" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return $tmp;
    }

    public static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = null;
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $line = array_map('trim', $line);
            if ($headers === null) {
                $headers = $line;
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $line[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    public static function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the .xlsx file.');
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            $zip->close();
            throw new \RuntimeException('Unsupported workbook layout (sheet1 not found).');
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = new \SimpleXMLElement($sharedXml);
            foreach ($sx->si as $si) {
                $parts = [];
                foreach ($si->t as $t) {
                    $parts[] = (string) $t;
                }
                $shared[] = implode('', $parts);
            }
        }
        $zip->close();

        $xml = new \SimpleXMLElement($sheet);
        $rows = [];
        foreach ($xml->sheetData->row as $rowEl) {
            $row = [];
            $colSeq = 0;
            foreach ($rowEl->c as $c) {
                $ref = (string) $c['r'];
                $col = $ref !== '' ? self::colIndex($ref) : $colSeq;
                $colSeq++;
                $type = (string) $c['t'];
                $raw = trim((string) $c->v);
                if ($type === 's') {
                    $val = $shared[(int) $raw] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) $c->is->t;
                } elseif ($type === 'str') {
                    $val = $raw;
                } else {
                    $val = $raw !== '' ? $raw + 0 : '';
                }
                $row[$col] = $val;
            }
            if ($row) {
                $rows[] = $row;
            }
        }

        $headers = array_shift($rows) ?? [];
        $named = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($headers as $idx => $h) {
                $out[(string) $h] = $row[$idx] ?? '';
            }
            $named[] = $out;
        }

        return $named;
    }

    public static function colIndex(string $ref): int
    {
        $letters = '';
        foreach (str_split($ref) as $ch) {
            if (ctype_alpha($ch)) {
                $letters .= $ch;
            } else {
                break;
            }
        }
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}