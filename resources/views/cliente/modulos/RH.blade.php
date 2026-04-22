@extends('layouts.cliente')

@section('title', 'Recursos Humanos')

@section('content')
@php
    $rtRh        = Route::has('cliente.modulos.rh') ? route('cliente.modulos.rh') : '#';
    $rtTimbres   = Route::has('cliente.modulos.timbres') ? route('cliente.modulos.timbres') : '#';
    $rtFact      = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
    $rtSat       = Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#';
    $rtVentas    = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
    $rtReportes  = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';

    $rhIcon = function (string $name): string {
        $icons = [
            'hero' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'employees' => '<svg viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9.5" cy="7" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M20 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15 4.2a3.2 3.2 0 0 1 0 6.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'schedule' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 13v4l2.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'cfdi' => '<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v5h5M8 13h8M8 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'payments' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="12" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18M7 15h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'calc' => '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'timbrado' => '<svg viewBox="0 0 24 24" fill="none"><path d="M8 4h8l3 3v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M9 13l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'sua' => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 20h16M6 20V9l6-4 6 4v11M9 20v-6h6v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'excel' => '<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M9 4v16M15 4v16M4 9h16M4 15h16" stroke="currentColor" stroke-width="1.8"/></svg>',
            'portal' => '<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 19h4M8 16h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'ai' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M18.5 15.5l.8 2 .8-2 2-.8-2-.8-.8-2-.8 2-2 .8 2 .8ZM5 16l1 2.5L8.5 19 6 20l-1 2.5L4 20l-2.5-1 2.5-1L5 16Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
            'estructura' => '<svg viewBox="0 0 24 24" fill="none"><rect x="10" y="3" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="4" y="17" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="10" y="17" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="16" y="17" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v4M6 17v-2h12v2M12 11H6m6 0h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'incidencias' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'prenomina' => '<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="3" width="16" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16.5 16.5l1.2 1.2 2.3-2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'expedientes' => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.8"/></svg>',
            'alta' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 19a6 6 0 0 1 12 0M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'upload' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 16V6M8.5 9.5 12 6l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 16.5v1A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'docs' => '<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8"/><path d="M8 13h8M8 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'org' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 4v4M6 12v-2h12v2M6 12v4M12 12v4M18 12v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="4" y="16" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="10" y="16" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="16" y="16" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="10" y="4" width="4" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 0 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 0 1-4 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 0 1 0-4h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 0 1 4 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6h.1a2 2 0 0 1 0 4h-.1a1 1 0 0 0-.9.6Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'vacaciones' => '<svg viewBox="0 0 24 24" fill="none"><path d="M7 20c4 0 7-2.7 7-6 0-1.9-1.2-3.6-3-4.7.1-3-1.8-5.3-5-6.3 1.4 2.6 1.4 4.6-.2 6.3C3.8 11 3 12.4 3 14c0 3.3 1.8 6 4 6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 17c0-2.5 2-4.5 4.5-4.5.2 0 .3 0 .5 0-.5 2.7-2.3 6.5-5 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'validate' => '<svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/></svg>',
            'alert' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.3 4.8 3.8 16A2 2 0 0 0 5.5 19h13a2 2 0 0 0 1.7-3l-6.5-11.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            'summary' => '<svg viewBox="0 0 24 24" fill="none"><path d="M7 5h10M7 10h10M7 15h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="4" y="3" width="16" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/></svg>',
            'layout' => '<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="4" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="4" y="13" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="13" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>',
            'download' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 4v10M8.5 10.5 12 14l3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 18h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'mail' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'mobile' => '<svg viewBox="0 0 24 24" fill="none"><rect x="7" y="3" width="10" height="18" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M11 18h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'audit' => '<svg viewBox="0 0 24 24" fill="none"><path d="M9 11h6M9 15h4M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8"/></svg>',
            'timbres' => '<svg viewBox="0 0 24 24" fill="none"><path d="M8 7a4 4 0 1 1 8 0c0 1.8.6 2.5 1.5 3.3.9.8 1.5 1.8 1.5 3.2H5c0-1.4.6-2.4 1.5-3.2C7.4 9.5 8 8.8 8 7Z" stroke="currentColor" stroke-width="1.8"/><path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'facturacion' => '<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h10v18l-2-1.5L12 21l-3-1.5L7 21V3Z" stroke="currentColor" stroke-width="1.8"/><path d="M9 8h6M9 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'sat' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3 4 7v5c0 5 3.4 8.3 8 9 4.6-.7 8-4 8-9V7l-8-4Z" stroke="currentColor" stroke-width="1.8"/><path d="M9.5 12 11 13.5 14.5 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'ventas' => '<svg viewBox="0 0 24 24" fill="none"><path d="M5 16V9M12 16V5M19 16v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 19h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'reportes' => '<svg viewBox="0 0 24 24" fill="none"><path d="M5 19V10M12 19V5M19 19v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 19h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        ];

        return $icons[$name] ?? $icons['hero'];
    };

    $kpis = [
        ['icon' => 'employees', 'label' => 'Empleados', 'value' => '0'],
        ['icon' => 'schedule', 'label' => 'Programadas', 'value' => '0'],
        ['icon' => 'cfdi', 'label' => 'CFDI', 'value' => '0'],
        ['icon' => 'payments', 'label' => 'Pagos', 'value' => '0'],
    ];

    $heroShortcuts = [
        ['icon' => 'calc', 'label' => 'Cálculo'],
        ['icon' => 'schedule', 'label' => 'Programación'],
        ['icon' => 'timbrado', 'label' => 'Timbrado'],
        ['icon' => 'payments', 'label' => 'Dispersión'],
        ['icon' => 'sua', 'label' => 'SUA'],
        ['icon' => 'excel', 'label' => 'Excel'],
        ['icon' => 'portal', 'label' => 'Portal'],
        ['icon' => 'ai', 'label' => 'IA'],
    ];

    $blocks = [
        [
            'id' => 'empleados', 'group' => 'operacion', 'icon' => 'employees', 'title' => 'Empleados',
            'count1' => '0', 'count1_label' => 'Activos', 'count2' => '0', 'count2_label' => 'Altas', 'count3' => '0', 'count3_label' => 'Pendientes',
            'chips' => ['Expedientes', 'Contratos', 'Salarios', 'Bancos'],
            'actions' => [
                ['icon' => 'expedientes', 'label' => 'Expedientes'],
                ['icon' => 'alta', 'label' => 'Alta'],
                ['icon' => 'upload', 'label' => 'Carga masiva'],
                ['icon' => 'docs', 'label' => 'Documentos'],
            ],
        ],
        [
            'id' => 'estructura', 'group' => 'operacion', 'icon' => 'estructura', 'title' => 'Estructura',
            'count1' => '0', 'count1_label' => 'Sucursales', 'count2' => '0', 'count2_label' => 'Áreas', 'count3' => '0', 'count3_label' => 'Puestos',
            'chips' => ['Sucursales', 'Departamentos', 'Puestos', 'Centros costo'],
            'actions' => [
                ['icon' => 'org', 'label' => 'Organización'],
                ['icon' => 'estructura', 'label' => 'Sucursales'],
                ['icon' => 'employees', 'label' => 'Puestos'],
                ['icon' => 'settings', 'label' => 'Configurar'],
            ],
        ],
        [
            'id' => 'incidencias', 'group' => 'operacion', 'icon' => 'incidencias', 'title' => 'Incidencias',
            'count1' => '0', 'count1_label' => 'Faltas', 'count2' => '0', 'count2_label' => 'Vacaciones', 'count3' => '0', 'count3_label' => 'Horas extra',
            'chips' => ['Faltas', 'Bonos', 'Descuentos', 'Vacaciones'],
            'actions' => [
                ['icon' => 'incidencias', 'label' => 'Faltas'],
                ['icon' => 'vacaciones', 'label' => 'Vacaciones'],
                ['icon' => 'schedule', 'label' => 'Horas extra'],
                ['icon' => 'upload', 'label' => 'Capturar'],
            ],
        ],
        [
            'id' => 'prenomina', 'group' => 'nomina', 'icon' => 'prenomina', 'title' => 'Pre-nómina',
            'count1' => '0', 'count1_label' => 'Pendientes', 'count2' => '0', 'count2_label' => 'Alertas', 'count3' => '0', 'count3_label' => 'Autorizadas',
            'chips' => ['Validación', 'Alertas', 'Comparativos', 'Autorización'],
            'actions' => [
                ['icon' => 'validate', 'label' => 'Validar'],
                ['icon' => 'alert', 'label' => 'Alertas'],
                ['icon' => 'summary', 'label' => 'Revisar'],
                ['icon' => 'timbrado', 'label' => 'Autorizar'],
            ],
        ],
        [
            'id' => 'calculo', 'group' => 'nomina', 'icon' => 'calc', 'title' => 'Cálculo',
            'count1' => '0', 'count1_label' => 'Ordinaria', 'count2' => '0', 'count2_label' => 'Extra', 'count3' => '0', 'count3_label' => 'Escenarios',
            'chips' => ['Semanal', 'Quincenal', 'Mensual', 'PTU / Aguinaldo'],
            'actions' => [
                ['icon' => 'calc', 'label' => 'Calcular'],
                ['icon' => 'schedule', 'label' => 'Recálculo'],
                ['icon' => 'reportes', 'label' => 'Escenarios'],
                ['icon' => 'summary', 'label' => 'Resumen'],
            ],
        ],
        [
            'id' => 'programacion', 'group' => 'nomina', 'icon' => 'schedule', 'title' => 'Programación',
            'count1' => '0', 'count1_label' => 'Calendarios', 'count2' => '0', 'count2_label' => 'Cierres', 'count3' => '0', 'count3_label' => 'Eventos',
            'chips' => ['Fechas', 'Automatización', 'Frecuencias', 'Recordatorios'],
            'actions' => [
                ['icon' => 'schedule', 'label' => 'Calendario'],
                ['icon' => 'alert', 'label' => 'Recordatorios'],
                ['icon' => 'timbrado', 'label' => 'Programar'],
                ['icon' => 'summary', 'label' => 'Periodos'],
            ],
        ],
        [
            'id' => 'cfdi', 'group' => 'cfdi', 'icon' => 'cfdi', 'title' => 'CFDI Nómina',
            'count1' => '0', 'count1_label' => 'Timbrados', 'count2' => '0', 'count2_label' => 'Pendientes', 'count3' => '0', 'count3_label' => 'Cancelados',
            'chips' => ['XML', 'PDF', 'Cancelación', 'Historial SAT'],
            'actions' => [
                ['icon' => 'timbrado', 'label' => 'Timbrar'],
                ['icon' => 'alert', 'label' => 'Cancelar'],
                ['icon' => 'cfdi', 'label' => 'PDF'],
                ['icon' => 'summary', 'label' => 'XML'],
            ],
        ],
        [
            'id' => 'pagos', 'group' => 'pagos', 'icon' => 'payments', 'title' => 'Pagos y dispersión',
            'count1' => '0', 'count1_label' => 'Por pagar', 'count2' => '0', 'count2_label' => 'Pagados', 'count3' => '0', 'count3_label' => 'Layouts',
            'chips' => ['Transferencia', 'Conciliación', 'Banco', 'Estatus'],
            'actions' => [
                ['icon' => 'payments', 'label' => 'Pagar'],
                ['icon' => 'sua', 'label' => 'Dispersar'],
                ['icon' => 'layout', 'label' => 'Layout'],
                ['icon' => 'validate', 'label' => 'Conciliar'],
            ],
        ],
        [
            'id' => 'sua', 'group' => 'cumplimiento', 'icon' => 'sua', 'title' => 'SUA / IMSS',
            'count1' => '0', 'count1_label' => 'Obligaciones', 'count2' => '0', 'count2_label' => 'Exportables', 'count3' => '0', 'count3_label' => 'Diferencias',
            'chips' => ['SUA', 'IMSS', 'INFONAVIT', 'ISR'],
            'actions' => [
                ['icon' => 'sua', 'label' => 'SUA'],
                ['icon' => 'sat', 'label' => 'IMSS'],
                ['icon' => 'estructura', 'label' => 'INFONAVIT'],
                ['icon' => 'download', 'label' => 'Exportar'],
            ],
        ],
        [
            'id' => 'excel', 'group' => 'operacion', 'icon' => 'excel', 'title' => 'Excel y plantillas',
            'count1' => '0', 'count1_label' => 'Plantillas', 'count2' => '0', 'count2_label' => 'Lotes', 'count3' => '0', 'count3_label' => 'Errores',
            'chips' => ['Importación', 'Complementos', 'Vista previa', 'Historial'],
            'actions' => [
                ['icon' => 'download', 'label' => 'Plantilla'],
                ['icon' => 'upload', 'label' => 'Importar'],
                ['icon' => 'summary', 'label' => 'Vista previa'],
                ['icon' => 'reportes', 'label' => 'Historial'],
            ],
        ],
        [
            'id' => 'portal', 'group' => 'empleado', 'icon' => 'portal', 'title' => 'Portal del empleado',
            'count1' => '0', 'count1_label' => 'Usuarios', 'count2' => '0', 'count2_label' => 'Recibos', 'count3' => '0', 'count3_label' => 'Solicitudes',
            'chips' => ['Recibos', 'PDF/XML', 'Vacaciones', 'Solicitudes'],
            'actions' => [
                ['icon' => 'portal', 'label' => 'Portal'],
                ['icon' => 'cfdi', 'label' => 'Recibos'],
                ['icon' => 'mail', 'label' => 'Solicitudes'],
                ['icon' => 'mobile', 'label' => 'Móvil'],
            ],
        ],
        [
            'id' => 'ia', 'group' => 'empleado', 'icon' => 'ai', 'title' => 'IA y auditoría',
            'count1' => '0', 'count1_label' => 'Alertas', 'count2' => '0', 'count2_label' => 'Simulaciones', 'count3' => '0', 'count3_label' => 'Auditorías',
            'chips' => ['Alertas', 'Riesgos', 'Simulación', 'Comparativos'],
            'actions' => [
                ['icon' => 'ai', 'label' => 'Asistente'],
                ['icon' => 'alert', 'label' => 'Alertas'],
                ['icon' => 'reportes', 'label' => 'Simular'],
                ['icon' => 'audit', 'label' => 'Auditar'],
            ],
        ],
    ];
@endphp

<link rel="stylesheet" href="{{ asset('assets/client/css/rh/rh-module.css') }}">

<div class="container-fluid rh-shell" id="rhModuleApp">
    <section class="rh-hero">
        <div class="rh-hero__left">
            <div class="rh-hero__badge">
                <span class="rh-svg-icon rh-svg-icon--xs">{!! $rhIcon('hero') !!}</span>
                <span>RH + Nómina</span>
            </div>

            <div class="rh-hero__title-wrap">
                <div class="rh-hero__icon">
                    <span class="rh-svg-icon">{!! $rhIcon('hero') !!}</span>
                </div>

                <div class="rh-hero__copy">
                    <h1>Recursos Humanos con Nómina</h1>
                    <p>Empleados, cálculo, timbrado, pagos, SUA, Excel, portal e IA.</p>
                </div>
            </div>

            <div class="rh-hero__shortcuts">
                @foreach($heroShortcuts as $shortcut)
                    <span class="rh-mini-chip">
                        <span class="rh-svg-icon rh-svg-icon--sm">{!! $rhIcon($shortcut['icon']) !!}</span>
                        <span>{{ $shortcut['label'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="rh-hero__right">
            <div class="rh-hero-panel">
                <div class="rh-hero-panel__top">
                    <div class="rh-hero-panel__logo">RH</div>
                    <div>
                        <div class="rh-hero-panel__title">Centro RH</div>
                        <div class="rh-hero-panel__sub">Operación compacta y escalable</div>
                    </div>
                </div>

                <div class="rh-hero-panel__stats">
                    <div class="rh-hero-stat">
                        <span>Módulos</span>
                        <strong>{{ count($blocks) }}</strong>
                    </div>
                    <div class="rh-hero-stat">
                        <span>IA</span>
                        <strong>1</strong>
                    </div>
                </div>

                <a href="#rhAccordions" class="rh-hero-panel__btn">Ver bloques</a>
            </div>
        </div>
    </section>

    <section class="rh-kpis">
        @foreach($kpis as $kpi)
            <article class="rh-kpi">
                <div class="rh-kpi__icon">
                    <span class="rh-svg-icon">{!! $rhIcon($kpi['icon']) !!}</span>
                </div>
                <div class="rh-kpi__info">
                    <strong>{{ $kpi['value'] }}</strong>
                    <span>{{ $kpi['label'] }}</span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="rh-toolbar">
        <div class="rh-toolbar__filters">
            <button type="button" class="rh-filter is-active" data-rh-filter="all">Todo</button>
            <button type="button" class="rh-filter" data-rh-filter="operacion">Operación</button>
            <button type="button" class="rh-filter" data-rh-filter="nomina">Nómina</button>
            <button type="button" class="rh-filter" data-rh-filter="cfdi">CFDI</button>
            <button type="button" class="rh-filter" data-rh-filter="pagos">Pagos</button>
            <button type="button" class="rh-filter" data-rh-filter="cumplimiento">SUA / IMSS</button>
            <button type="button" class="rh-filter" data-rh-filter="empleado">Portal / IA</button>
        </div>

        <div class="rh-toolbar__actions">
            <button type="button" class="rh-toolbar-btn" data-rh-expand="all">Expandir todo</button>
            <button type="button" class="rh-toolbar-btn" data-rh-collapse="all">Contraer todo</button>
        </div>
    </section>

    <section class="rh-accordions" id="rhAccordions">
        @foreach($blocks as $index => $block)
            <article class="rh-accordion" data-rh-filter-group="{{ $block['group'] }}">
                <button
                    type="button"
                    class="rh-accordion__head {{ $index === 0 ? 'is-open' : '' }}"
                    data-rh-accordion-trigger
                    aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                    aria-controls="rh-panel-{{ $block['id'] }}"
                >
                    <div class="rh-accordion__left">
                        <div class="rh-accordion__icon">
                            <span class="rh-svg-icon">{!! $rhIcon($block['icon']) !!}</span>
                        </div>

                        <div class="rh-accordion__title-wrap">
                            <h3>{{ $block['title'] }}</h3>

                            <div class="rh-accordion__chips">
                                @foreach($block['chips'] as $chip)
                                    <span class="rh-accordion-chip">{{ $chip }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="rh-accordion__right">
                        <div class="rh-counter">
                            <span>{{ $block['count1_label'] }}</span>
                            <strong>{{ $block['count1'] }}</strong>
                        </div>
                        <div class="rh-counter">
                            <span>{{ $block['count2_label'] }}</span>
                            <strong>{{ $block['count2'] }}</strong>
                        </div>
                        <div class="rh-counter">
                            <span>{{ $block['count3_label'] }}</span>
                            <strong>{{ $block['count3'] }}</strong>
                        </div>

                        <div class="rh-accordion__toggle">
                            <span>+</span>
                        </div>
                    </div>
                </button>

                <div
                    id="rh-panel-{{ $block['id'] }}"
                    class="rh-accordion__body {{ $index === 0 ? 'is-open' : '' }}"
                    data-rh-accordion-body
                >
                    <div class="rh-accordion__actions">
                        @foreach($block['actions'] as $action)
                            <button type="button" class="rh-action-btn">
                                <span class="rh-action-btn__icon">
                                    <span class="rh-svg-icon">{!! $rhIcon($action['icon']) !!}</span>
                                </span>
                                <span>{{ $action['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </article>
        @endforeach
    </section>
</div>

<script src="{{ asset('assets/client/js/rh/rh-module.js') }}"></script>
@endsection