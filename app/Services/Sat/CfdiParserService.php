<?php

declare(strict_types=1);

namespace App\Services\Sat;

use Illuminate\Support\Facades\Log;

final class CfdiParserService
{
    /**
     * Parser robusto CFDI 3.2 / 3.3 / 4.0
     *
     * Devuelve arreglo normalizado:
     * - cfdi_version, fecha, uuid
     * - rfc/razon emisor + receptor
     * - subtotal, descuento, iva (trasladado 002), iva_pct, total
     * - traslados_total, retenciones_total, ret_iva, ret_isr
     * - breakdown opcional (por impuesto)
     */
    public function parse(string $xmlContent): ?array
    {
        $xmlContent = trim($xmlContent);
        if ($xmlContent === '') {
            return null;
        }

        try {
            $xml = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                return null;
            }

            $ns = $xml->getNamespaces(true);
            $cfdiNs = $ns['cfdi'] ?? null;

            $root = $xml; // Comprobante

            $a = $root->attributes();

            $version = $this->attr($a, ['Version', 'version']);
            $fecha   = $this->attr($a, ['Fecha', 'fecha']);

            $subtotal  = $this->toFloat($this->attr($a, ['SubTotal','subTotal','subtotal']));
            $total     = $this->toFloat($this->attr($a, ['Total','total']));
            $descuento = $this->toFloat($this->attr($a, ['Descuento','descuento']));

            if ($subtotal <= 0) {
                $subtotal = $this->sumConceptImportes($root, $cfdiNs);
            }

            [$rfcEmisor, $razonEmisor] = $this->readEmisor($root, $cfdiNs);
            [$rfcReceptor, $razonReceptor] = $this->readReceptor($root, $cfdiNs);

            $uuid = $this->readUuid($root, $cfdiNs);

            // Si no hay UUID ni fecha, lo descartamos
            if ($uuid === '' && $fecha === '') {
                return null;
            }

            // Impuestos: preferimos detalle para IVA 002; evitamos doble conteo
            $taxAgg = $this->readTaxes($root, $cfdiNs, $version);

            $iva = (float)($taxAgg['iva_trasladado'] ?? 0.0);

            // ===== Fallbacks conservadores =====
            // Total esperado ≈ (subtotal - descuento) + traslados - retenciones
            // Si no tenemos IVA pero sí total/subtotal, inferimos solo si no hay más señales
            if ($iva <= 0 && $subtotal > 0 && $total > 0) {
                $base = $subtotal - max(0.0, $descuento);

                // Si hay retenciones, no adjudicamos todo el diff a IVA.
                $retTot = (float)($taxAgg['retenciones_total'] ?? 0.0);
                $trasTot = (float)($taxAgg['traslados_total'] ?? 0.0);

                // Caso común: no hay detalle pero total > base
                $diff = $total - $base;

                // Si ya tenemos traslados_total (aunque no sepamos desglose), úsalo.
                if ($iva <= 0 && $trasTot > 0) {
                    $iva = $trasTot;
                } else {
                    // Solo si no hay retenciones ni otros traslados conocidos, asignamos diff a IVA
                    $hayRet = $retTot > 0.00001;
                    if (!$hayRet && $diff > 0.009) {
                        $iva = round($diff, 2);
                    }
                }
            }

            // Si total viene en 0, reconstruir con lo que tengamos
            if ($total <= 0 && $subtotal > 0) {
                $base = $subtotal - max(0.0, $descuento);
                $total = round($base + max(0.0, $iva) - max(0.0, (float)($taxAgg['retenciones_total'] ?? 0.0)), 2);
            }

            $ivaPct = 0.0;
            if ($subtotal > 0 && $iva > 0) {
                $ivaPct = round(($iva / $subtotal) * 100, 6);
            }

            return [
                'cfdi_version'   => $version !== '' ? $version : null,
                'fecha'          => $fecha !== '' ? $fecha : null,
                'uuid'           => $uuid,

                'rfc_emisor'     => $rfcEmisor,
                'razon_emisor'   => $razonEmisor,

                'rfc_receptor'   => $rfcReceptor,
                'razon_receptor' => $razonReceptor,

                'subtotal'       => round($subtotal, 2),
                'descuento'      => round($descuento, 2),
                'iva'            => round($iva, 2),
                'iva_pct'        => $ivaPct,
                'total'          => round($total, 2),

                'traslados_total'   => round((float)($taxAgg['traslados_total'] ?? 0.0), 2),
                'retenciones_total' => round((float)($taxAgg['retenciones_total'] ?? 0.0), 2),
                'ret_iva'           => round((float)($taxAgg['ret_iva'] ?? 0.0), 2),
                'ret_isr'           => round((float)($taxAgg['ret_isr'] ?? 0.0), 2),

                // desglose (opcional)
                'tax_breakdown'     => $taxAgg['breakdown'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('[CfdiParserService] parse error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /* ============================================================
     * Helpers base
     * ============================================================ */

    private function attr($attrs, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($attrs[$k]) && (string)$attrs[$k] !== '') {
                return (string)$attrs[$k];
            }
        }
        return '';
    }

    private function toFloat($v): float
    {
        if ($v === null) return 0.0;
        if (is_float($v) || is_int($v)) return (float)$v;

        $s = trim((string)$v);
        if ($s === '') return 0.0;

        $s = preg_replace('/[^0-9\.\-]/', '', $s) ?: '';
        if ($s === '' || $s === '-' || $s === '.') return 0.0;

        $n = (float)$s;
        return is_finite($n) ? $n : 0.0;
    }

    private function node(\SimpleXMLElement $root, ?string $cfdiNs, array $names): ?\SimpleXMLElement
    {
        foreach ($names as $name) {
            if ($cfdiNs) {
                $child = $root->children($cfdiNs)->{$name} ?? null;
                if ($child instanceof \SimpleXMLElement) return $child;
            }

            $child2 = $root->{$name} ?? null;
            if ($child2 instanceof \SimpleXMLElement) return $child2;
        }
        return null;
    }

    private function sumConceptImportes(\SimpleXMLElement $root, ?string $cfdiNs): float
    {
        $sum = 0.0;

        try {
            $conceptos = $this->node($root, $cfdiNs, ['Conceptos']);
            if (!$conceptos) return 0.0;

            foreach ($conceptos->children($cfdiNs ?: null) as $c) {
                if ($c->getName() !== 'Concepto') continue;
                $a = $c->attributes();
                $importe = $this->toFloat($this->attr($a, ['Importe','importe']));
                if ($importe > 0) $sum += $importe;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $sum > 0 ? $sum : 0.0;
    }

    private function readEmisor(\SimpleXMLElement $root, ?string $cfdiNs): array
    {
        $rfc = '';
        $razon = '';

        try {
            $emisor = $this->node($root, $cfdiNs, ['Emisor']);
            if ($emisor) {
                $a = $emisor->attributes();
                $rfc = (string)($a['Rfc'] ?? $a['RFC'] ?? $a['rfc'] ?? '');
                $razon = (string)($a['Nombre'] ?? $a['nombre'] ?? '');
            }
        } catch (\Throwable $e) {}

        return [strtoupper(trim($rfc)), trim($razon)];
    }

    private function readReceptor(\SimpleXMLElement $root, ?string $cfdiNs): array
    {
        $rfc = '';
        $razon = '';

        try {
            $rec = $this->node($root, $cfdiNs, ['Receptor']);
            if ($rec) {
                $a = $rec->attributes();
                $rfc = (string)($a['Rfc'] ?? $a['RFC'] ?? $a['rfc'] ?? '');
                $razon = (string)($a['Nombre'] ?? $a['nombre'] ?? '');
            }
        } catch (\Throwable $e) {}

        return [strtoupper(trim($rfc)), trim($razon)];
    }

    private function readUuid(\SimpleXMLElement $root, ?string $cfdiNs): string
    {
        $uuid = '';

        try {
            $comp = $this->node($root, $cfdiNs, ['Complemento']);
            if (!$comp) return '';

            $compNs = $comp->getNamespaces(true);

            // 1) prefijo tfd típico
            if (isset($compNs['tfd'])) {
                $tfd = $comp->children($compNs['tfd'])->TimbreFiscalDigital ?? null;
                if ($tfd) {
                    $a = $tfd->attributes();
                    $uuid = (string)($a['UUID'] ?? $a['Uuid'] ?? '');
                    if ($uuid !== '') return $uuid;
                }
            }

            // 2) barrido
            foreach ($compNs as $url) {
                $child = $comp->children($url)->TimbreFiscalDigital ?? null;
                if ($child) {
                    $a = $child->attributes();
                    $uuid = (string)($a['UUID'] ?? $a['Uuid'] ?? '');
                    if ($uuid !== '') return $uuid;
                }
            }
        } catch (\Throwable $e) {}

        return $uuid;
    }

    /* ============================================================
     * Impuestos (sin doble conteo)
     * ============================================================ */

    private function readTaxes(\SimpleXMLElement $root, ?string $cfdiNs, string $version): array
    {
        $breakdownTras = []; // [impuesto => importe]
        $breakdownRet  = []; // [impuesto => importe]

        $ivaTrasladado = 0.0;
        $retIva = 0.0;
        $retIsr = 0.0;

        // 1) Impuestos a nivel comprobante (detalle)
        $impComprobante = $this->node($root, $cfdiNs, ['Impuestos']);
        if ($impComprobante) {
            $det = $this->sumTaxesFromImpuestosNode($impComprobante, $cfdiNs, $version);
            $this->mergeBreakdown($breakdownTras, $det['breakdown_tras'] ?? []);
            $this->mergeBreakdown($breakdownRet,  $det['breakdown_ret'] ?? []);

            $ivaTrasladado += (float)($det['iva_trasladado'] ?? 0.0);
            $retIva        += (float)($det['ret_iva'] ?? 0.0);
            $retIsr        += (float)($det['ret_isr'] ?? 0.0);
        }

        // 2) Impuestos por concepto
        $conceptos = $this->node($root, $cfdiNs, ['Conceptos']);
        if ($conceptos) {
            foreach ($conceptos->children($cfdiNs ?: null) as $c) {
                if ($c->getName() !== 'Concepto') continue;

                $imp = $this->node($c, $cfdiNs, ['Impuestos']);
                if (!$imp) continue;

                $det = $this->sumTaxesFromImpuestosNode($imp, $cfdiNs, $version);
                $this->mergeBreakdown($breakdownTras, $det['breakdown_tras'] ?? []);
                $this->mergeBreakdown($breakdownRet,  $det['breakdown_ret'] ?? []);

                $ivaTrasladado += (float)($det['iva_trasladado'] ?? 0.0);
                $retIva        += (float)($det['ret_iva'] ?? 0.0);
                $retIsr        += (float)($det['ret_isr'] ?? 0.0);
            }
        }

        // Totales por suma (SIN duplicar)
        $trasladosTotal   = array_sum($breakdownTras);
        $retencionesTotal = array_sum($breakdownRet);

        // 3) Si el comprobante trae TotalImpuestosTrasladados/Retenidos, úsalo solo como fallback (no se suma)
        if ($impComprobante) {
            $a = $impComprobante->attributes();

            $tit = $this->toFloat($this->attr($a, ['TotalImpuestosTrasladados','totalImpuestosTrasladados']));
            if ($tit > 0 && $trasladosTotal <= 0.0) {
                $trasladosTotal = $tit;
            }

            $tir = $this->toFloat($this->attr($a, ['TotalImpuestosRetenidos','totalImpuestosRetenidos']));
            if ($tir > 0 && $retencionesTotal <= 0.0) {
                $retencionesTotal = $tir;
            }
        }

        // Normalizar IVA: si no pudimos identificar 002 pero solo hay un traslado, usarlo como aproximación
        if ($ivaTrasladado <= 0.0 && $trasladosTotal > 0.0) {
            // si existe 002 en breakdown, úsalo
            if (isset($breakdownTras['002']) && $breakdownTras['002'] > 0) {
                $ivaTrasladado = $breakdownTras['002'];
            } elseif (count(array_filter($breakdownTras, fn($v) => $v > 0)) === 1) {
                $ivaTrasladado = $trasladosTotal;
            }
        }

        return [
            'traslados_total'   => max(0.0, (float)$trasladosTotal),
            'retenciones_total' => max(0.0, (float)$retencionesTotal),
            'iva_trasladado'    => max(0.0, (float)$ivaTrasladado),
            'ret_iva'           => max(0.0, (float)$retIva),
            'ret_isr'           => max(0.0, (float)$retIsr),
            'breakdown' => [
                'traslados'   => $this->roundAssoc($breakdownTras),
                'retenciones' => $this->roundAssoc($breakdownRet),
            ],
        ];
    }

    private function sumTaxesFromImpuestosNode(\SimpleXMLElement $impNode, ?string $cfdiNs, string $version): array
    {
        $ivaTrasladado = 0.0;
        $retIva = 0.0;
        $retIsr = 0.0;

        $bTras = []; // [impuesto => importe]
        $bRet  = []; // [impuesto => importe]

        // Traslados
        $traslados = $this->node($impNode, $cfdiNs, ['Traslados']);
        if ($traslados) {
            foreach ($traslados->children($cfdiNs ?: null) as $t) {
                if ($t->getName() !== 'Traslado') continue;

                $ta = $t->attributes();

                $impuesto   = strtoupper(trim((string)($ta['Impuesto'] ?? $ta['impuesto'] ?? '')));
                $tipoFactor = strtoupper(trim((string)($ta['TipoFactor'] ?? $ta['tipoFactor'] ?? '')));
                $base       = $this->toFloat($this->attr($ta, ['Base','base']));
                $tasaOCuota = $this->toFloat($this->attr($ta, ['TasaOCuota','tasaOCuota','tasa','Tasa']));
                $importe    = $this->toFloat($this->attr($ta, ['Importe','importe']));

                if ($tipoFactor === 'EXENTO') {
                    $importe = 0.0;
                }

                // Reconstruir si falta Importe
                if ($importe <= 0 && $base > 0 && $tasaOCuota > 0 && $tipoFactor !== 'EXENTO') {
                    $rate = $this->normalizeRate($tasaOCuota, $version);
                    $importe = round($base * $rate, 6);
                }

                if ($importe <= 0) continue;

                // Si no hay impuesto, NO lo asumimos como IVA; lo marcamos UNK_TRAS
                if ($impuesto === '') $impuesto = 'UNK_TRAS';

                $bTras[$impuesto] = (float)($bTras[$impuesto] ?? 0.0) + $importe;

                // IVA típico: 002 o texto IVA
                if ($impuesto === '002' || $impuesto === 'IVA') {
                    $ivaTrasladado += $importe;
                }
            }
        }

        // Retenciones
        $rets = $this->node($impNode, $cfdiNs, ['Retenciones']);
        if ($rets) {
            foreach ($rets->children($cfdiNs ?: null) as $r) {
                if ($r->getName() !== 'Retencion') continue;

                $ra = $r->attributes();
                $impuesto = strtoupper(trim((string)($ra['Impuesto'] ?? $ra['impuesto'] ?? '')));
                $importe  = $this->toFloat($this->attr($ra, ['Importe','importe']));
                if ($importe <= 0) continue;

                if ($impuesto === '') $impuesto = 'UNK_RET';

                $bRet[$impuesto] = (float)($bRet[$impuesto] ?? 0.0) + $importe;

                if ($impuesto === '001' || $impuesto === 'ISR') {
                    $retIsr += $importe;
                } elseif ($impuesto === '002' || $impuesto === 'IVA') {
                    $retIva += $importe;
                }
            }
        }

        return [
            'iva_trasladado' => $ivaTrasladado,
            'ret_iva'        => $retIva,
            'ret_isr'        => $retIsr,
            'breakdown_tras' => $bTras,
            'breakdown_ret'  => $bRet,
        ];
    }

    private function normalizeRate(float $tasaOCuota, string $version): float
    {
        // Heurística segura:
        // - si >= 1 => porcentaje (ej. 16.00)
        // - si < 1 => factor (0.160000)
        if ($tasaOCuota >= 1.0) {
            return $tasaOCuota / 100.0;
        }
        return $tasaOCuota;
    }

    private function mergeBreakdown(array &$dst, array $src): void
    {
        foreach ($src as $k => $v) {
            $dst[$k] = (float)($dst[$k] ?? 0.0) + (float)$v;
        }
    }

    private function roundAssoc(array $a): array
    {
        foreach ($a as $k => $v) {
            $a[$k] = round((float)$v, 2);
        }
        return $a;
    }
}
