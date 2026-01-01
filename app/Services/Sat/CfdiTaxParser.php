<?php

namespace App\Services\Sat;

use Illuminate\Support\Facades\Log;

class CfdiTaxParser
{
    /**
     * Parser principal.
     *
     * @return array{
     *   ok: bool,
     *   version: string|null,
     *   uuid: string|null,
     *   fecha: string|null,
     *   subtotal: float,
     *   total: float,
     *   iva: float,              // IVA trasladado (Impuesto 002 / IVA)
     *   iva_retenido: float,     // IVA retenido (si existe)
     *   isr_retenido: float,     // ISR retenido (si existe)
     *   total_traslados: float,  // suma de todos los traslados (cualquier impuesto)
     *   total_retenciones: float,// suma de todas las retenciones
     *   taxes: array<int, array<string, mixed>>, // detalle normalizado
     *   error: string|null
     * }
     */
    public function parse(string $xml): array
    {
        $out = [
            'ok'               => false,
            'version'          => null,
            'uuid'             => null,
            'fecha'            => null,
            'subtotal'         => 0.0,
            'total'            => 0.0,
            'iva'              => 0.0,
            'iva_retenido'     => 0.0,
            'isr_retenido'     => 0.0,
            'total_traslados'  => 0.0,
            'total_retenciones'=> 0.0,
            'taxes'            => [],
            'error'            => null,
        ];

        $xml = trim($xml);
        if ($xml === '') {
            $out['error'] = 'XML vacío';
            return $out;
        }

        // Seguridad: algunos XML vienen con BOM
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;

        try {
            libxml_use_internal_errors(true);

            $sx = simplexml_load_string($xml);
            if (!$sx) {
                $out['error'] = $this->libxmlErrorsToString();
                return $out;
            }

            // Registrar namespaces para xpath robusto (sin depender del prefijo)
            $this->registerAllNamespaces($sx);

            // Localiza Comprobante (raíz)
            $comprobante = $this->xpathFirst($sx, '/*[local-name()="Comprobante"]');
            if (!$comprobante) {
                $out['error'] = 'No se encontró nodo Comprobante';
                return $out;
            }

            $version = $this->attr($comprobante, ['Version', 'version']);
            $out['version'] = $version ?: null;

            $out['fecha'] = $this->attr($comprobante, ['Fecha', 'fecha']) ?: null;

            // SubTotal y Total (comprobante)
            $out['subtotal'] = $this->num($this->attr($comprobante, ['SubTotal', 'subTotal', 'subtotal']));
            $out['total']    = $this->num($this->attr($comprobante, ['Total', 'total']));

            // UUID del Timbre Fiscal Digital
            $tfd = $this->xpathFirst($sx, '//*[local-name()="TimbreFiscalDigital"]');
            if ($tfd) {
                $out['uuid'] = $this->attr($tfd, ['UUID', 'Uuid', 'uuid']) ?: null;
            }

            // Extraer impuestos desde nodos Impuestos
            // CFDI 3.2: Comprobante/Impuestos/Traslados/Traslado y Retenciones/Retencion
            // CFDI 3.3/4.0: Comprobante/Impuestos/Traslados/Traslado (Impuesto=002 etc, TipoFactor, TasaOCuota, Importe)
            $traslados = $sx->xpath('//*[local-name()="Impuestos"]/*[local-name()="Traslados"]/*[local-name()="Traslado"]') ?: [];
            $retenciones = $sx->xpath('//*[local-name()="Impuestos"]/*[local-name()="Retenciones"]/*[local-name()="Retencion"]') ?: [];

            // Suma trasladados
            foreach ($traslados as $t) {
                $tax = $this->normalizeTaxNode($t, 'traslado', $version);

                // Exento en 3.3/4.0: TipoFactor="Exento" (sin Importe)
                if (($tax['tipo_factor'] ?? '') === 'Exento') {
                    $tax['importe'] = 0.0;
                } else {
                    // Si no viene Importe, intenta Base * TasaOCuota
                    if (($tax['importe'] ?? 0) <= 0 && ($tax['base'] ?? 0) > 0 && ($tax['tasa'] ?? 0) > 0) {
                        $tax['importe'] = round(($tax['base'] * $tax['tasa']), 6);
                    }
                }

                $importe = (float)($tax['importe'] ?? 0.0);
                $out['total_traslados'] += $importe;

                // IVA: CFDI 3.3/4.0 => Impuesto = "002"
                // CFDI 3.2 => impuesto = "IVA" (a veces "002" también existe en algunos generadores viejos)
                $isIva = false;
                $imp = (string)($tax['impuesto'] ?? '');
                if ($imp === '002' || strtoupper($imp) === 'IVA') {
                    $isIva = true;
                }

                if ($isIva) {
                    $out['iva'] += $importe;
                }

                $out['taxes'][] = $tax;
            }

            // Suma retenciones
            foreach ($retenciones as $r) {
                $tax = $this->normalizeTaxNode($r, 'retencion', $version);

                if (($tax['importe'] ?? 0) <= 0 && ($tax['base'] ?? 0) > 0 && ($tax['tasa'] ?? 0) > 0) {
                    $tax['importe'] = round(($tax['base'] * $tax['tasa']), 6);
                }

                $importe = (float)($tax['importe'] ?? 0.0);
                $out['total_retenciones'] += $importe;

                $imp = (string)($tax['impuesto'] ?? '');
                if ($imp === '002' || strtoupper($imp) === 'IVA') {
                    $out['iva_retenido'] += $importe;
                }
                if ($imp === '001' || strtoupper($imp) === 'ISR') {
                    $out['isr_retenido'] += $importe;
                }

                $out['taxes'][] = $tax;
            }

            // Redondeos finales (2 decimales para montos visibles; conserva más en detalle si quieres)
            $out['iva']               = $this->money2($out['iva']);
            $out['iva_retenido']      = $this->money2($out['iva_retenido']);
            $out['isr_retenido']      = $this->money2($out['isr_retenido']);
            $out['total_traslados']   = $this->money2($out['total_traslados']);
            $out['total_retenciones'] = $this->money2($out['total_retenciones']);

            // Fallback si Total/SubTotal vienen en 0 pero hay info suficiente
            // - Si total vacío pero subtotal + traslados - retenciones existe
            if ($out['total'] <= 0 && $out['subtotal'] > 0) {
                $calc = $out['subtotal'] + $out['total_traslados'] - $out['total_retenciones'];
                $out['total'] = $this->money2($calc);
            }

            // - Si subtotal vacío pero total y traslados existen y NO hay retenciones
            if ($out['subtotal'] <= 0 && $out['total'] > 0) {
                $calcSub = $out['total'] - $out['total_traslados'] + $out['total_retenciones'];
                if ($calcSub > 0) {
                    $out['subtotal'] = $this->money2($calcSub);
                }
            }

            $out['ok'] = true;
            return $out;

        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            Log::warning('[CFDI:parser] parse error', [
                'error' => $e->getMessage(),
            ]);
            return $out;
        } finally {
            libxml_clear_errors();
        }
    }

    /**
     * Normaliza un nodo de impuesto (Traslado o Retencion) para CFDI 3.2 / 3.3 / 4.0
     */
    private function normalizeTaxNode(\SimpleXMLElement $node, string $tipo, ?string $version): array
    {
        // CFDI 3.3/4.0 atributos: Base, Impuesto, TipoFactor, TasaOCuota, Importe
        // CFDI 3.2 atributos: base (no siempre), impuesto (IVA/ISR), tasa, importe
        // Ojo: en 3.2 "tasa" suele venir como "16.00" (porcentaje) NO 0.160000

        $impuesto = $this->attr($node, ['Impuesto', 'impuesto']) ?? '';

        $base     = $this->num($this->attr($node, ['Base', 'base']));
        $importe  = $this->num($this->attr($node, ['Importe', 'importe']));

        $tipoFactor = $this->attr($node, ['TipoFactor', 'tipoFactor']) ?? null;

        // tasa: en 3.3/4.0 viene como 0.160000 (factor)
        // en 3.2 viene como 16.00 (porcentaje) o 0.16 según emisor
        $tasaRaw = $this->attr($node, ['TasaOCuota', 'tasa', 'tasaocuota', 'tasaOCuota']) ?? null;
        $tasaNum = $this->num($tasaRaw);

        // Convertir tasa 3.2 si parece porcentaje (16 => 0.16)
        // Heurística: si es > 1.0 y <= 100, lo tratamos como porcentaje
        if ($tasaNum > 1.0 && $tasaNum <= 100.0) {
            $tasaNum = $tasaNum / 100.0;
        }

        // Para consistencia: impuesto "IVA" / "ISR" a código si hace falta
        // IVA => 002, ISR => 001
        $impUp = strtoupper((string)$impuesto);
        if ($impUp === 'IVA') $impuesto = '002';
        if ($impUp === 'ISR') $impuesto = '001';

        return [
            'tipo'        => $tipo, // traslado / retencion
            'version'     => $version,
            'impuesto'    => (string)$impuesto,
            'tipo_factor' => $tipoFactor ? (string)$tipoFactor : null,
            'tasa'        => (float)$tasaNum,   // factor (0.16)
            'base'        => (float)$base,
            'importe'     => (float)$importe,
            'raw'         => [
                'tasa_raw' => $tasaRaw,
            ],
        ];
    }

    private function registerAllNamespaces(\SimpleXMLElement $sx): void
    {
        $namespaces = $sx->getNamespaces(true) ?: [];
        foreach ($namespaces as $prefix => $uri) {
            // prefix vacío no se registra para xpath con prefijo,
            // pero no lo necesitamos porque usamos local-name()
            if ($prefix !== '') {
                $sx->registerXPathNamespace($prefix, $uri);
            }
        }
    }

    private function xpathFirst(\SimpleXMLElement $sx, string $xpath): ?\SimpleXMLElement
    {
        $arr = $sx->xpath($xpath);
        if (!$arr || !isset($arr[0])) return null;
        return $arr[0];
    }

    private function attr(\SimpleXMLElement $node, array $names): ?string
    {
        foreach ($names as $n) {
            if (isset($node[$n])) {
                $v = (string)$node[$n];
                $v = trim($v);
                if ($v !== '') return $v;
            }
        }
        return null;
    }

    private function num(?string $v): float
    {
        if ($v === null) return 0.0;
        $s = trim((string)$v);
        if ($s === '') return 0.0;
        // limpia $ y comas si vinieran (raro en CFDI, pero por seguridad)
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? $s;
        $n = (float)$s;
        return is_finite($n) ? $n : 0.0;
    }

    private function money2(float $n): float
    {
        // Redondeo típico de visualización
        return round($n, 2);
    }

    private function libxmlErrorsToString(): string
    {
        $errs = libxml_get_errors();
        if (!$errs) return 'Error desconocido al parsear XML';
        $msg = [];
        foreach ($errs as $e) {
            $msg[] = trim($e->message) . " (line {$e->line})";
        }
        return implode(' | ', array_slice($msg, 0, 5));
    }
}
