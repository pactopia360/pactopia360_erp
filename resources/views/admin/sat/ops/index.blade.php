{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\sat\ops\index.blade.php --}}

@extends('layouts.admin')

@section('title','Dashboard SAT Administrativo')
@section('pageClass','page-admin-sat-ops-v3')
@section('contentLayout','full')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/sat-ops.css') }}?v={{ filemtime(public_path('assets/admin/css/sat-ops.css')) }}">
@endpush

@php
    use Illuminate\Support\Facades\Route;

    $routes = [
        'dashboard'    => Route::has('admin.sat.ops.index') ? route('admin.sat.ops.index') : '#',
        'rfcs'         => Route::has('admin.sat.ops.rfcs.index') ? route('admin.sat.ops.rfcs.index') : '#',
        'credentials'  => Route::has('admin.sat.ops.credentials.index') ? route('admin.sat.ops.credentials.index') : '#',
        'downloads'    => Route::has('admin.sat.ops.downloads.index') ? route('admin.sat.ops.downloads.index') : '#',
        'manual'       => Route::has('admin.sat.ops.manual.index') ? route('admin.sat.ops.manual.index') : '#',
        'payments'     => Route::has('admin.sat.ops.payments.index') ? route('admin.sat.ops.payments.index') : '#',
        'prices'       => Route::has('admin.sat.prices.index') ? route('admin.sat.prices.index') : '#',
        'discounts'    => Route::has('admin.sat.discounts.index') ? route('admin.sat.discounts.index') : '#',
        'vaultAccess'  => Route::has('admin.billing.vault_access.index') ? route('admin.billing.vault_access.index') : '#',
    ];

    $cards = [
        [
            'title'   => 'RFCs',
            'desc'    => 'Alta, edición y control administrativo de RFCs operativos.',
            'icon'    => '🧾',
            'url'     => $routes['rfcs'],
            'action'  => 'Administrar RFCs',
            'enabled' => $routes['rfcs'] !== '#',
        ],
        [
            'title'   => 'Credenciales SAT',
            'desc'    => 'Gestión de CER, KEY, validación y limpieza de credenciales.',
            'icon'    => '🔐',
            'url'     => $routes['credentials'],
            'action'  => 'Ver credenciales',
            'enabled' => $routes['credentials'] !== '#',
        ],
        [
            'title'   => 'Descargas y archivos',
            'desc'    => 'Operación de metadata, XML, reportes, CFDI y archivos SAT.',
            'icon'    => '📦',
            'url'     => $routes['downloads'],
            'action'  => 'Abrir módulo',
            'enabled' => $routes['downloads'] !== '#',
        ],
        [
            'title'   => 'Precios SAT',
            'desc'    => 'Reglas de precios, rangos, unitarios y orden operativo.',
            'icon'    => '💳',
            'url'     => $routes['prices'],
            'action'  => 'Configurar precios',
            'enabled' => $routes['prices'] !== '#',
        ],
        [
            'title'   => 'Descuentos',
            'desc'    => 'Códigos, vigencias, límites y activación comercial.',
            'icon'    => '🏷️',
            'url'     => $routes['discounts'],
            'action'  => 'Gestionar descuentos',
            'enabled' => $routes['discounts'] !== '#',
        ],
        [
            'title'   => 'Pagos SAT',
            'desc'    => 'Seguimiento operativo de pagos y control administrativo.',
            'icon'    => '💰',
            'url'     => $routes['payments'],
            'action'  => 'Ver pagos',
            'enabled' => $routes['payments'] !== '#',
        ],
        [
            'title'   => 'Solicitudes manuales',
            'desc'    => 'Gestión de solicitudes especiales y flujo operativo interno.',
            'icon'    => '🛠️',
            'url'     => $routes['manual'],
            'action'  => 'Abrir solicitudes',
            'enabled' => $routes['manual'] !== '#',
        ],
        [
            'title'   => 'Accesos a bóveda',
            'desc'    => 'Administración de accesos, usuarios y operación de bóveda.',
            'icon'    => '📚',
            'url'     => $routes['vaultAccess'],
            'action'  => 'Administrar accesos',
            'enabled' => $routes['vaultAccess'] !== '#',
        ],
    ];

    $quickLinks = [
        ['label' => 'RFCs',         'url' => $routes['rfcs'],        'icon' => '🧾'],
        ['label' => 'Credenciales', 'url' => $routes['credentials'], 'icon' => '🔐'],
        ['label' => 'Descargas',    'url' => $routes['downloads'],   'icon' => '📦'],
        ['label' => 'Precios',      'url' => $routes['prices'],      'icon' => '💳'],
        ['label' => 'Descuentos',   'url' => $routes['discounts'],   'icon' => '🏷️'],
        ['label' => 'Pagos',        'url' => $routes['payments'],    'icon' => '💰'],
        ['label' => 'Manuales',     'url' => $routes['manual'],      'icon' => '🛠️'],
        ['label' => 'Bóveda',       'url' => $routes['vaultAccess'], 'icon' => '📚'],
    ];

    $availableModules = collect($cards)->where('enabled', true)->count();
@endphp

@section('page-header')
    <div class="satOpsHero satOpsHero--clean">
        <div class="satOpsHero__main">
            <div class="satOpsHero__content">
                <div class="satOpsHero__eyebrow">PACTOPIA360 · ADMIN · SAT</div>
                <h1 class="satOpsHero__title">Dashboard SAT Administrativo</h1>
                <p class="satOpsHero__subtitle">
                    Panel central para operar RFCs, credenciales, descargas, precios, descuentos,
                    pagos, solicitudes manuales y accesos de bóveda desde una sola vista.
                </p>

                <div class="satOpsHero__actions">
                    <a href="{{ $routes['downloads'] }}" class="satOpsBtn satOpsBtn--primary">
                        Abrir descargas
                    </a>
                    <a href="{{ $routes['credentials'] }}" class="satOpsBtn">
                        Ver credenciales
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
<div class="satOpsWrap">
    <section class="satOpsBoard satOpsBoard--clean">
        <div class="satOpsBoard__main">
            <section class="satOpsSection">
                <div class="satOpsSectionHead satOpsSectionHead--clean">
                    <div>
                        <h2 class="satOpsSectionTitle">Módulos principales</h2>
                        <p class="satOpsSectionText">
                            Accesos directos para la operación diaria del área SAT administrativa.
                        </p>
                    </div>

                    <div class="satOpsSectionMeta">
                        <span class="satOpsKicker">{{ $availableModules }} módulos disponibles</span>
                    </div>
                </div>

                <div class="satOpsGrid satOpsGrid--4">
                    @foreach($cards as $card)
                        @if($card['enabled'])
                            <a href="{{ $card['url'] }}" class="satOpsCard satOpsCard--clean">
                                <div class="satOpsCard__top">
                                    <div class="satOpsCard__icon">{{ $card['icon'] }}</div>
                                    <span class="satOpsBadge satOpsBadge--on">Activo</span>
                                </div>

                                <div class="satOpsCard__body">
                                    <div class="satOpsCard__title">{{ $card['title'] }}</div>
                                    <div class="satOpsCard__desc">{{ $card['desc'] }}</div>
                                </div>

                                <div class="satOpsCard__bottom">
                                    <span>{{ $card['action'] }}</span>
                                    <strong>→</strong>
                                </div>
                            </a>
                        @else
                            <div class="satOpsCard satOpsCard--clean is-disabled">
                                <div class="satOpsCard__top">
                                    <div class="satOpsCard__icon">{{ $card['icon'] }}</div>
                                    <span class="satOpsBadge satOpsBadge--off">Pendiente</span>
                                </div>

                                <div class="satOpsCard__body">
                                    <div class="satOpsCard__title">{{ $card['title'] }}</div>
                                    <div class="satOpsCard__desc">{{ $card['desc'] }}</div>
                                </div>

                                <div class="satOpsCard__bottom">
                                    <span>No disponible</span>
                                    <strong>—</strong>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="satOpsBoard__aside">
            <div class="satOpsPanel satOpsPanel--clean">
                <div class="satOpsPanel__head">
                    <h3 class="satOpsPanel__title">Accesos rápidos</h3>
                    <p class="satOpsPanel__text">
                        Navegación directa a los módulos más usados.
                    </p>
                </div>

                <div class="satOpsQuickActions">
                    @foreach($quickLinks as $link)
                        @if($link['url'] !== '#')
                            <a href="{{ $link['url'] }}" class="satOpsMiniAction">
                                <span class="satOpsMiniAction__icon">{{ $link['icon'] }}</span>
                                <div>
                                    <strong>{{ $link['label'] }}</strong>
                                    <small>Abrir módulo</small>
                                </div>
                            </a>
                        @else
                            <div class="satOpsMiniAction is-disabled">
                                <span class="satOpsMiniAction__icon">{{ $link['icon'] }}</span>
                                <div>
                                    <strong>{{ $link['label'] }}</strong>
                                    <small>Pendiente</small>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="satOpsPanel satOpsPanel--clean">
                <div class="satOpsPanel__head">
                    <h3 class="satOpsPanel__title">Alcance del dashboard</h3>
                </div>

                <ul class="satOpsList satOpsList--clean">
                    <li>Control de RFCs operativos</li>
                    <li>Gestión de credenciales SAT</li>
                    <li>Descargas, metadata, XML y reportes</li>
                    <li>Precios y descuentos SAT</li>
                    <li>Pagos y solicitudes manuales</li>
                    <li>Administración de accesos a bóveda</li>
                </ul>
            </div>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/sat-ops.js') }}?v={{ filemtime(public_path('assets/admin/js/sat-ops.js')) }}"></script>
@endpush