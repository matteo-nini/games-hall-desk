<?php
/**
 * XlsxWriter — writer XLSX minimale senza dipendenze esterne.
 *
 * Ogni cella è un array [valore, tipo, stile] dove:
 *   tipo  's'=stringa, 'n'=numero
 *   stile  0=normale, 1=grassetto, 2=valuta, 3=grassetto+valuta
 */
class XlsxWriter
{
    private array $rows = [];
    private string $sheetName;

    public function __construct(string $sheetName = 'Foglio1')
    {
        $this->sheetName = $sheetName;
    }

    public function addRow(array $cells): void
    {
        $this->rows[] = $cells;
    }

    public function output(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Cache-Control: max-age=0');
        echo $this->build();
    }

    private function build(): string
    {
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        $content = file_get_contents($tmp);
        unlink($tmp);
        return $content;
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function colRef(int $col): string
    {
        $ref = '';
        while ($col >= 0) {
            $ref = chr(65 + ($col % 26)) . $ref;
            $col = intdiv($col, 26) - 1;
        }
        return $ref;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $n = $this->esc($this->sheetName);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $n . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00\ &quot;€&quot;"/></numFmts>'
            . '<fonts count="2">'
            .   '<font><sz val="10"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="10"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            .   '<fill><patternFill patternType="none"/></fill>'
            .   '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            .   '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>'            // 0 normale
            .   '<xf numFmtId="0"   fontId="1" fillId="0" borderId="0" xfId="0"/>'            // 1 grassetto
            .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' // 2 valuta
            .   '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' // 3 grassetto+valuta
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function sheet(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<sheetData>';

        foreach ($this->rows as $ri => $cells) {
            $rowNum = $ri + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($cells as $ci => [$val, $type, $style]) {
                $ref = $this->colRef($ci) . $rowNum;
                $s   = $style > 0 ? ' s="' . $style . '"' : '';
                if ($type === 'n') {
                    $xml .= '<c r="' . $ref . '"' . $s . '><v>' . ($val === null ? '' : (float)$val) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t>' . $this->esc((string)$val) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }
}
