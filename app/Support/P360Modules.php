<?php declare(strict_types=1);

namespace App\Support;

final class P360Modules
{
    /**
     * Catálogo único (SOT) de módulos del sistema.
     * Mantén aquí LAS KEYS que usarán:
     * - Admin (guardar meta.modules_state)
     * - Cliente (leer y pintar menú + gate de rutas)
     */
    public static function catalog(): array
    {
        // key => [label, group]
        return [
            // Cuenta
            'mi_cuenta'      => ['label' => 'Mi cuenta',        'group' => 'Cuenta'],
            'estado_cuenta'  => ['label' => 'Estado de cuenta', 'group' => 'Cuenta'],
            'pagos'          => ['label' => 'Pagos',            'group' => 'Cuenta'],
            'facturas'       => ['label' => 'Facturas',         'group' => 'Cuenta'],

            // Operación / CFDI
            'facturacion'    => ['label' => 'Facturación',      'group' => 'Operación'],

            // SAT / bóveda
            'sat_descargas'  => ['label' => 'SAT (Descarga)',   'group' => 'SAT'],
            'boveda_fiscal'  => ['label' => 'Bóveda Fiscal',    'group' => 'SAT'],

            // ERP
            'crm'            => ['label' => 'CRM',              'group' => 'ERP'],
            'nomina'         => ['label' => 'Nómina',           'group' => 'ERP'],
            'pos'            => ['label' => 'Punto de venta',   'group' => 'ERP'],
            'inventario'     => ['label' => 'Inventario',       'group' => 'ERP'],
            'reportes'       => ['label' => 'Reportes',         'group' => 'ERP'],
            'integraciones'  => ['label' => 'Integraciones',    'group' => 'ERP'],

            // Comunicación / comercial
            'alertas'        => ['label' => 'Alertas',          'group' => 'Otros'],
            'chat'           => ['label' => 'Chat',             'group' => 'Otros'],
            'marketplace'    => ['label' => 'Marketplace',      'group' => 'Comercial'],
        ];
    }

    /**
     * Estado default por módulo si una cuenta NO tiene configuración guardada.
     * Recomendación:
     * - visible: true (aparezca en menú)
     * - enabled: false (si no está definido, no debería funcionar)
     * - blocked: true (mostrar candado)
     *
     * Si quieres “todo activo por defecto”, cambia enabled=true y blocked=false.
     */
    public static function defaultState(): array
    {
        $out = [];
        foreach (self::catalog() as $key => $def) {
            $out[$key] = [
                'visible' => true,
                'enabled' => false,
                'blocked' => true,
            ];
        }
        return $out;
    }

    /**
     * Sanitiza y normaliza un arreglo de estados (SOT Admin) contra el catálogo.
     */
    public static function normalizeState(array $raw): array
    {
        $catalog = self::catalog();
        $out     = self::defaultState();

        foreach ($catalog as $key => $_) {
            $v = $raw[$key] ?? null;
            if (!is_array($v)) continue;

            $out[$key] = [
                'visible' => isset($v['visible']) ? (bool)$v['visible'] : $out[$key]['visible'],
                'enabled' => isset($v['enabled']) ? (bool)$v['enabled'] : $out[$key]['enabled'],
                'blocked' => isset($v['blocked']) ? (bool)$v['blocked'] : $out[$key]['blocked'],
            ];
        }

        return $out;
    }

    /**
     * Flags derivadas (para checks rápidos en vista y gates).
     */
    public static function toFlags(array $state): array
    {
        $flags = [];
        foreach ($state as $k => $s) {
            $visible = !empty($s['visible']);
            $enabled = !empty($s['enabled']) && empty($s['blocked']);
            $blocked = !empty($s['blocked']);

            $flags[$k] = [
                'visible' => $visible,
                'enabled' => $enabled,
                'blocked' => $blocked,
            ];
        }
        return $flags;
    }
}
