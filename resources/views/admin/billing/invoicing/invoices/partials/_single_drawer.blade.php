{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\partials\_single_drawer.blade.php --}}

@php
    $routeCreate = route('admin.billing.invoicing.invoices.create');
@endphp

<div class="invx-drawer invx-drawer--disabled" id="singleDrawer" aria-hidden="true" hidden>
    <div class="invx-drawer__backdrop" data-invx-drawer-close></div>

    <aside class="invx-drawer__panel invx-drawer__panel--sm" role="dialog" aria-modal="true" aria-labelledby="singleDrawerTitle">
        <div class="invx-drawer__head">
            <div>
                <h3 class="invx-drawer__title" id="singleDrawerTitle">Nueva factura</h3>
                <p class="invx-drawer__sub">La captura ahora se realiza en una pantalla completa para trabajar más cómodo.</p>
            </div>

            <button type="button" class="invx-iconbtn" data-invx-drawer-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="invx-drawer__body">
            <div class="invx-empty" style="margin:0;">
                Este formulario ya no se usa dentro del emergente.
            </div>

            <div class="invx-form-actions" style="margin-top:14px;">
                <button type="button" class="invx-btn invx-btn--soft" data-invx-drawer-close>Cerrar</button>
                <a href="{{ $routeCreate }}" class="invx-btn invx-btn--primary">Abrir pantalla de captura</a>
            </div>
        </div>
    </aside>
</div>