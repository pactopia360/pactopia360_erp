<?php

namespace App\Services\Sat;

use ZipArchive;

class ReportBuilder
{
    /**
     * Lee una carpeta con XMLs CFDI y regresa filas normalizadas + resumen.
     * @return array{rows: array<int,array>, summary: array<string,float>}
     */
    public function buildFromXmlDir(string $xmlDir, string $tipoEtiqueta = 'Emitidos'): array
    {
        $rows = [];
        $sum = [
            'subtotal' => 0.0,
            'iva'      => 0.0,
            'total'    => 0.0,
            'count'    => 0.0,
        ];

        if (is_dir($xmlDir)) {
            foreach (glob(rtrim($xmlDir, '/\\') . '/*.xml') as $file) {
                try {
                    $doc = simplexml_load_file($file);
                    if (!$doc) continue;
                    $doc->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

                    $comp = $doc->xpath('/cfdi:Comprobante')[0] ?? null;
                    $em   = $doc->xpath('/cfdi:Comprobante/cfdi:Emisor')[0] ?? null;
                    $re   = $doc->xpath('/cfdi:Comprobante/cfdi:Receptor')[0] ?? null;
                    $uuid = $doc->xpath('/cfdi:Comprobante/cfdi:Complemento/*[local-name()="TimbreFiscalDigital"]/@UUID')[0] ?? null;

                    if (!$comp) continue;

                    $fecha   = (string)($comp['Fecha'] ?? '');
                    $serie   = (string)($comp['Serie'] ?? '');
                    $folio   = (string)($comp['Folio'] ?? '');
                    $tipoC   = (string)($comp['TipoDeComprobante'] ?? '');
                    $sub     = (float)($comp['SubTotal'] ?? 0);
                    $tot     = (float)($comp['Total'] ?? 0);
                    $rfcEm   = (string)($em['Rfc'] ?? '');
                    $nomEm   = (string)($em['Nombre'] ?? '');
                    $uuidStr = (string)$uuid;

                    // IVA: suma traslados con Impuesto = 002 (IVA)
                    $iva = 0.0;
                    foreach ($doc->xpath('//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') as $t) {
                        $imp = (string)($t['Impuesto'] ?? '');
                        if ($imp === '002') {
                            $iva += (float)($t['Importe'] ?? 0);
                        }
                    }

                    $rows[] = [
                        // Encabezados solicitados:
                        // Folio, Fecha, RFC, Nombre o Razon Social, Serie, Folio, UUID, TIPO, Vigente/Cancelado, Subtotal, IVA, Total, Tipo (Emitido/Recibido)
                        $folio ?: ($serie.$folio),
                        substr($fecha, 0, 10),
                        $rfcEm,
                        $nomEm,
                        $serie,
                        $folio,
                        $uuidStr,
                        $tipoC,                   // TIPO (I,E,P,N,etc)
                        'Vigente',                // Sin consulta al SAT: por defecto Vigente
                        number_format($sub, 2, '.', ''),
                        number_format($iva, 2, '.', ''),
                        number_format($tot, 2, '.', ''),
                        $tipoEtiqueta,            // Emitidos/Recibidos (etiqueta)
                    ];

                    $sum['subtotal'] += $sub;
                    $sum['iva']      += $iva;
                    $sum['total']    += $tot;
                    $sum['count']    += 1;
                } catch (\Throwable $e) {
                    // Ignora XML inválido
                }
            }
        }

        return ['rows' => $rows, 'summary' => $sum];
    }

    /** CSV en string (UTF-8 con BOM) */
    public function asCsv(array $rows, array $summary): string
    {
        $out = fopen('php://temp','r+');
        // BOM
        fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['Folio','Fecha','RFC','Nombre o Razon Social','Serie','Folio','UUID','TIPO','Vigente/Cancelado','Subtotal','IVA','Total','Tipo (Emitido/Recibido)']);

        foreach ($rows as $r) {
            fputcsv($out, $r);
        }

        // Línea vacía + resumen
        fputcsv($out, []);
        fputcsv($out, ['Resumen','','','','','','','','',
            number_format($summary['subtotal'] ?? 0, 2, '.', ''),
            number_format($summary['iva'] ?? 0, 2, '.', ''),
            number_format($summary['total'] ?? 0, 2, '.', ''),
            'Registros: '.(int)($summary['count'] ?? 0)
        ]);

        rewind($out);
        return stream_get_contents($out) ?: '';
    }

    /** XLSX minimalista (OpenXML) como binario */
    public function asXlsx(array $rows, array $summary): string
    {
        $tmp = tmpfile();
        $tmpPath = stream_get_meta_data($tmp)['uri'];

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows, $summary));

        $zip->close();

        $bin = file_get_contents($tmpPath);
        fclose($tmp);
        return $bin ?: '';
    }

    private function xml($s): string { return htmlspecialchars((string)$s, ENT_XML1|ENT_COMPAT, 'UTF-8'); }
    private function colLetter(int $n): string { $s=''; while($n>0){$n--; $s=chr($n%26+65).$s; $n=intdiv($n,26);} return $s; }

    private function sheetXml(array $rows, array $summary): string
    {
        $header = ['Folio','Fecha','RFC','Nombre o Razon Social','Serie','Folio','UUID','TIPO','Vigente/Cancelado','Subtotal','IVA','Total','Tipo (Emitido/Recibido)'];

        $xmlRows = [];
        $rowIdx = 1;

        // header
        $cells = [];
        foreach ($header as $i => $h) {
            $ref = $this->colLetter($i+1).$rowIdx;
            $cells[] = '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->xml($h).'</t></is></c>';
        }
        $xmlRows[] = '<row r="'.$rowIdx.'">'.implode('', $cells).'</row>';
        $rowIdx++;

        // data
        foreach ($rows as $r) {
            $cells = [];
            foreach ($r as $i => $v) {
                $ref = $this->colLetter($i+1).$rowIdx;
                $cells[] = '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->xml($v).'</t></is></c>';
            }
            $xmlRows[] = '<row r="'.$rowIdx.'">'.implode('', $cells).'</row>';
            $rowIdx++;
        }

        // blank
        $xmlRows[] = '<row r="'.($rowIdx++).'"/>';

        // summary row
        $sum = [
            '', '', '', '', '', '', '', '', '',
            number_format($summary['subtotal'] ?? 0, 2, '.', ''),
            number_format($summary['iva'] ?? 0, 2, '.', ''),
            number_format($summary['total'] ?? 0, 2, '.', ''),
            'Registros: '.(int)($summary['count'] ?? 0),
        ];
        $cells = [];
        foreach ($sum as $i => $v) {
            $ref = $this->colLetter($i+1).$rowIdx;
            $cells[] = '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->xml($v).'</t></is></c>';
        }
        $xmlRows[] = '<row r="'.$rowIdx.'">'.implode('', $cells).'</row>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>'.implode('', $xmlRows).'</sheetData>
</worksheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }
    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }
    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }
    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Reporte" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
    }
}
