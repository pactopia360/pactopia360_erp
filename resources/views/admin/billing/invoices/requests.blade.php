{{-- resources/views/admin/billing/invoices/requests.blade.php (v2.3 · HUB-first · UI CLARO · ZIP real + email-ready real) --}}
@extends('layouts.admin')

@section('title', 'Facturación · Solicitudes de factura')
@section('pageClass', 'p360-invoice-requests')

@php
  $mode   = $mode ?? 'hub';      // hub|legacy|missing
  $q      = $q ?? request('q','');
  $status = $status ?? request('status','');
  $period = $period ?? request('period','');

  // Status UI por modo:
  // legacy: requested|in_progress|done|rejected
  // hub:    requested|in_progress|issued|rejected  (si quieres usar issued)
  $statusOptions = $mode === 'legacy'
      ? ['requested','in_progress','done','rejected']
      : ['requested','in_progress','issued','rejected'];

  // Para filtrar: si viene "invoiced" viejo, lo convertimos a done/issued solo para UI.
  if ($status === 'invoiced') $status = ($mode === 'legacy') ? 'done' : 'issued';
@endphp

@push('styles')
  @php
    $cssRel = 'assets/admin/css/billing/invoice-requests.css';
    $cssAbs = public_path($cssRel);
    $cssUrl = (is_file($cssAbs) && filesize($cssAbs) > 16)
      ? asset($cssRel).'?v='.filemtime($cssAbs)
      : asset($cssRel).'?v='.time();
  @endphp
  <link rel="stylesheet" href="{{ $cssUrl }}">
@endpush

@section('content')
<div class="p360-wrap">

  <div class="p360-top">
    <div class="p360-title">
      <h1>Solicitudes de factura</h1>
      <div class="sub">
        Administración de solicitudes (HUB-first). Modo actual:
        <span class="mono">{{ $mode }}</span>
      </div>
    </div>

    <div class="badges">
      <span class="pill">
        <span class="dot {{ $mode==='hub' ? 'ok' : ($mode==='legacy' ? 'warn' : 'bad') }}"></span>
        <span class="mono">
          {{ $mode==='hub' ? 'billing_invoice_requests' : ($mode==='legacy' ? 'invoice_requests' : 'sin tabla') }}
        </span>
      </span>

      <a class="btn ghost" href="{{ url()->current() }}">Limpiar</a>
    </div>
  </div>

  <div class="p360-card">

    <div class="p360-toolbar">
      <form class="filters" method="GET" action="{{ url()->current() }}">
        <div class="field">
          <div class="label">Buscar</div>
          <input name="q" value="{{ $q }}" placeholder="Cuenta, RFC, UUID, notas, periodo…">
        </div>

        <div class="field">
          <div class="label">Periodo</div>
          <input name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>

        <div class="field">
          <div class="label">Estatus</div>
          <select name="status">
            <option value="" @selected($status==='')>Todos</option>
            @foreach($statusOptions as $s)
              <option value="{{ $s }}" @selected($status===$s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>

        <div class="field" style="justify-content:flex-end">
          <div class="label">&nbsp;</div>
          <button class="btn primary" type="submit">Filtrar</button>
        </div>
      </form>

      <div class="badges">
        @php
          $total = 0;
          if (is_object($rows) && method_exists($rows,'total')) $total = (int)$rows->total();
          elseif (is_iterable($rows)) $total = is_countable($rows) ? count($rows) : 0;
        @endphp
        <span class="pill">
          <span class="dot"></span>
          <span>Total: <span class="mono">{{ $total }}</span></span>
        </span>
      </div>
    </div>

    <div class="alerts">
      @if(!empty($error))
        <div class="alert warn">{{ $error }}</div>
      @endif

      @if(session('ok'))
        <div class="alert ok">{{ session('ok') }}</div>
      @endif

      @if(session('warn'))
        <div class="alert warn">{{ session('warn') }}</div>
      @endif

      @if(session('bad'))
        <div class="alert bad">{{ session('bad') }}</div>
      @endif

      @if($errors->any())
        <div class="alert bad">{{ $errors->first() }}</div>
      @endif
    </div>

    @if(method_exists($rows,'links') || is_iterable($rows))
      {{-- ✅ Cards (mobile) --}}
      <div class="cards" style="display:none">
        @foreach($rows as $r)
          @php
            $stRaw = (string)($r->status ?? '');
            if ($stRaw === '') $stRaw = 'requested';

            $stUi = strtolower(trim($stRaw));
            if ($stUi === 'invoiced') $stUi = ($mode==='legacy') ? 'done' : 'issued';
            if (!in_array($stUi, $statusOptions, true)) $stUi = 'requested';

            $name = trim((string)($r->account_name ?? ''));
            $rfc  = trim((string)($r->account_rfc ?? ''));
            $mail = trim((string)($r->account_email ?? ''));
            $acct = (string)($r->account_id ?? '—');

            $uuid  = (string)($r->cfdi_uuid ?? '');
            $notes = trim((string)($r->notes ?? ''));
            $per   = (string)($r->period ?? '—');

            $zipPath = (string)($r->zip_path ?? '');
            $hasZip  = trim($zipPath) !== '';

            $zipLabel = $hasZip ? 'ZIP listo' : 'ZIP no adjunto';
            $zipDot   = $hasZip ? 'ok' : 'warn';

            $dotForStatus = ($stUi==='rejected') ? 'bad' : (($stUi==='requested') ? 'warn' : 'ok');
          @endphp

          <div class="cardRow">
            <div class="cardTop">
              <div>
                <div class="mono">ID: {{ $r->id }} · {{ $acct }}</div>
                <div class="mut">{{ $name !== '' ? e($name) : '—' }} @if($rfc !== '') · <span class="mono">{{ $rfc }}</span>@endif</div>
                @if($mail !== '') <div class="mut">Email: <span class="mono">{{ $mail }}</span></div> @endif
              </div>

              <div>
                <span class="statusPill {{ $stUi }}">
                  <span class="dot {{ $dotForStatus }}"></span>
                  <span class="mono">{{ $stUi }}</span>
                </span>
                <div class="mut" style="margin-top:8px">
                  <span class="pill" style="padding:6px 10px">
                    <span class="dot {{ $zipDot }}"></span>
                    <span class="mono">{{ $zipLabel }}</span>
                  </span>
                </div>
              </div>
            </div>

            <div class="cardMeta">
              <div class="mut">Periodo: <span class="mono">{{ $per }}</span></div>
              <div class="mut">UUID: <span class="mono">{{ $uuid !== '' ? $uuid : '—' }}</span></div>
              <div class="mut">Notas: {{ $notes !== '' ? e($notes) : '—' }}</div>

              {{-- Botones (usan los mismos modales) --}}
              <div class="actionBar" style="margin-top:10px">
                <button type="button" class="btn mini info js-open-modal"
                  data-modal="m-edit"
                  data-id="{{ (int)$r->id }}"
                  data-status="{{ $stUi }}"
                  data-uuid="{{ e($uuid) }}"
                  data-notes="{{ e($notes) }}"
                  data-period="{{ e($per) }}"
                  data-account="{{ e($acct) }}"
                  data-email="{{ e($mail) }}"
                >Editar</button>

                <button type="button" class="btn mini primary js-open-modal"
                  data-modal="m-attach"
                  data-id="{{ (int)$r->id }}"
                  data-uuid="{{ e($uuid) }}"
                  data-period="{{ e($per) }}"
                  data-account="{{ e($acct) }}"
                >PDF/XML</button>

                <button type="button" class="btn mini primary js-open-modal"
                  data-modal="m-email"
                  data-id="{{ (int)$r->id }}"
                  data-period="{{ e($per) }}"
                  data-email="{{ e($mail) }}"
                >Enviar</button>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @push('scripts')
      <script>
      (function(){
        // muestra cards en <=760
        function syncCards(){
          const cards = document.querySelector('.p360-invoice-requests .cards');
          const table = document.querySelector('.p360-invoice-requests .tablewrap');
          if(!cards || !table) return;
          const isMobile = window.matchMedia('(max-width: 760px)').matches;
          cards.style.display = isMobile ? 'grid' : 'none';
          table.style.display = isMobile ? 'none' : 'block';
        }
        addEventListener('load', syncCards, {once:true});
        addEventListener('resize', syncCards);
      })();
      </script>
      @endpush



      <div class="tablewrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th style="width:260px">Cuenta</th>
              <th style="width:120px">Periodo</th>
              <th style="width:170px">Estatus</th>
              <th>Detalle</th>
              <th style="width:560px">Acciones</th>
            </tr>
          </thead>

          <tbody>
          @forelse($rows as $r)
            @php
              $stRaw = (string)($r->status ?? '');
              if ($stRaw === '') $stRaw = 'requested';

              // normalización UI:
              // legacy: requested|in_progress|done|rejected
              // hub:    requested|in_progress|issued|rejected
              $stUi = strtolower(trim($stRaw));
              if ($stUi === 'invoiced') $stUi = ($mode==='legacy') ? 'done' : 'issued';
              if (!in_array($stUi, $statusOptions, true)) $stUi = 'requested';

              $name = trim((string)($r->account_name ?? ''));
              $rfc  = trim((string)($r->account_rfc ?? ''));
              $mail = trim((string)($r->account_email ?? ''));
              $acct = (string)($r->account_id ?? '—');

              $uuid  = (string)($r->cfdi_uuid ?? '');
              $notes = trim((string)($r->notes ?? ''));
              $per   = (string)($r->period ?? '—');

              $zipPath = (string)($r->zip_path ?? '');
              $zipDisk = (string)($r->zip_disk ?? '');
              $hasZip  = trim($zipPath) !== '';

              $zipLabel = $hasZip ? 'ZIP listo' : 'ZIP no adjunto';
              $zipDot   = $hasZip ? 'ok' : 'warn';

              $dotForStatus = ($stUi==='rejected') ? 'bad' : (($stUi==='requested') ? 'warn' : 'ok');
            @endphp

            <tr>
              <td class="mono">{{ $r->id }}</td>

              <td>
                <div class="mono">{{ $acct }}</div>
                <div class="mut">
                  {{ $name !== '' ? e($name) : '—' }}
                  @if($rfc !== '') · <span class="mono">{{ $rfc }}</span>@endif
                </div>
                @if($mail !== '')
                  <div class="mut">Email: <span class="mono">{{ $mail }}</span></div>
                @endif
              </td>

              <td class="mono">{{ $per }}</td>

              <td>
                <span class="statusPill {{ $stUi }}">
                  <span class="dot {{ $dotForStatus }}"></span>
                  <span class="mono">{{ $stUi }}</span>
                </span>

                <div class="mut" style="margin-top:8px">
                  <span class="pill" style="padding:6px 10px">
                    <span class="dot {{ $zipDot }}"></span>
                    <span class="mono">{{ $zipLabel }}</span>
                  </span>
                </div>
              </td>

              <td>
                <div class="mut">UUID</div>
                <div class="mono">{{ $uuid !== '' ? $uuid : '—' }}</div>

                <div class="mut" style="margin-top:8px">Notas</div>
                <div class="notesCell">{{ $notes !== '' ? e($notes) : '—' }}</div>

                @if($hasZip)
                  <div class="mut" style="margin-top:8px">ZIP</div>
                  <div class="mono" style="word-break:break-all">
                    {{ $zipDisk !== '' ? ($zipDisk.':') : '' }}{{ $zipPath }}
                  </div>
                @endif
              </td>

              <td>
                  @php
                    // data para modals
                    $rowId    = (int) $r->id;
                    $accId    = (string) ($r->account_id ?? '');
                    $perUi    = (string) ($r->period ?? ($r->periodo ?? ''));
                    $uuidUi   = (string) ($r->cfdi_uuid ?? '');
                    $notesUi  = (string) ($r->notes ?? '');
                    $emailUi  = (string) ($r->account_email ?? ($r->email ?? ''));
                    $nameUi   = (string) ($r->account_name ?? '');
                    $rfcUi    = (string) ($r->account_rfc ?? ($r->rfc ?? ''));
                  @endphp

                  <div class="actionBar">
                    <button
                      type="button"
                      class="btn mini info js-open-modal"
                      data-modal="m-edit"
                      data-id="{{ $rowId }}"
                      data-status="{{ $stUi }}"
                      data-uuid="{{ e($uuidUi) }}"
                      data-notes="{{ e($notesUi) }}"
                      data-period="{{ e($perUi) }}"
                      data-account="{{ e($accId) }}"
                      data-email="{{ e($emailUi) }}"
                      data-name="{{ e($nameUi) }}"
                      data-rfc="{{ e($rfcUi) }}"
                    >
                      Editar solicitud
                    </button>

                    <button
                      type="button"
                      class="btn mini primary js-open-modal"
                      data-modal="m-attach"
                      data-id="{{ $rowId }}"
                      data-uuid="{{ e($uuidUi) }}"
                      data-period="{{ e($perUi) }}"
                      data-account="{{ e($accId) }}"
                    >
                      Adjuntar PDF/XML
                    </button>

                    <button
                      type="button"
                      class="btn mini primary js-open-modal"
                      data-modal="m-email"
                      data-id="{{ $rowId }}"
                      data-period="{{ e($perUi) }}"
                      data-email="{{ e($emailUi) }}"
                    >
                      Enviar “Factura lista”
                    </button>
                  </div>

                  <div class="mut actionHint">
                    Flujo recomendado: 1) Editar/Guardar (estatus/UUID/notas/ZIP) · 2) Adjuntar PDF/XML (opcional) · 3) Enviar correo.
                  </div>
                </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="mut" style="padding:18px">
                No hay solicitudes con los filtros actuales.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    @endif

    @if(method_exists($rows,'links'))
      <div class="pagination">{!! $rows->links() !!}</div>
    @endif

    <div class="footer">
      <div>
        @if($mode==='hub')
          Operando sobre <span class="mono">billing_invoice_requests</span>.
        @elseif($mode==='legacy')
          Operando sobre <span class="mono">invoice_requests</span> (legacy).
        @else
          No hay tabla disponible.
        @endif
      </div>
      <div class="mono">Pactopia360 · Admin Billing</div>
    </div>

  </div>
</div>

{{-- =========================
     MODALS (UI)
     ========================= --}}
<div class="p360-modal" id="p360-modal" aria-hidden="true">
  <div class="p360-modal__backdrop js-close-modal"></div>

  <div class="p360-modal__panel" role="dialog" aria-modal="true" aria-labelledby="p360-modal-title">
    <div class="p360-modal__head">
      <div>
        <div class="p360-modal__title" id="p360-modal-title">Modal</div>
        <div class="p360-modal__sub mono" id="p360-modal-sub">—</div>
      </div>
      <button type="button" class="p360-modal__x js-close-modal" aria-label="Cerrar">×</button>
    </div>

    <div class="p360-modal__body">

      {{-- A) EDITAR (status/uuid/notas/zip) --}}
      <section class="p360-modal__section" data-pane="m-edit" hidden>
        <form method="POST"
              id="form-edit"
              action=""
              data-action-template="{{ route('admin.billing.invoices.requests.status', ['id' => '__ID__']) }}"
              enctype="multipart/form-data"
              novalidate>
          @csrf
          <input type="hidden" name="_p360_row_id" id="edit-row-id" value="">

          <div class="grid2">
            <div class="field">
              <div class="label">Estatus</div>
              <select name="status" id="edit-status" required>
                @foreach($statusOptions as $s)
                  <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
              </select>
            </div>

            <div class="field">
              <div class="label">UUID (opcional)</div>
              <input name="cfdi_uuid"
                    id="edit-uuid"
                    placeholder="UUID"
                    value=""
                    autocomplete="off"
                    spellcheck="false">
            </div>
          </div>

          <div class="field" style="margin-top:10px">
            <div class="label">Notas (opcional)</div>
            <textarea name="notes"
                      id="edit-notes"
                      rows="3"
                      placeholder="Notas…"
                      maxlength="5000"></textarea>
          </div>

          <div class="field" style="margin-top:10px">
            <div class="label">ZIP (opcional)</div>
            <input type="file" name="zip" accept=".zip,application/zip,application/x-zip-compressed">
            <div class="mut" style="margin-top:6px">
              Si adjuntas ZIP aquí, se guarda en la solicitud y se usará para el email “Factura lista”.
            </div>
          </div>

          <div class="p360-modal__foot">
            <button type="button" class="btn ghost js-close-modal">Cancelar</button>
            <button type="submit" class="btn primary">Guardar</button>
          </div>
        </form>
      </section>

      {{-- B) ADJUNTAR PDF/XML --}}
      <section class="p360-modal__section" data-pane="m-attach" hidden>
        <form method="POST"
              id="form-attach"
              action=""
              data-action-template="{{ route('admin.billing.invoices.requests.attach', ['id' => '__ID__']) }}"
              enctype="multipart/form-data"
              novalidate>
          @csrf
          <input type="hidden" name="_p360_row_id" id="attach-row-id" value="">

          <div class="grid2">
            <div class="field">
              <div class="label">UUID (opcional)</div>
              <input name="cfdi_uuid"
                    id="attach-uuid"
                    placeholder="UUID"
                    value=""
                    autocomplete="off"
                    spellcheck="false">
            </div>

            <div class="field">
              <div class="label">Notas (opcional)</div>
              <input name="notes"
                    id="attach-notes"
                    placeholder="Notas…"
                    maxlength="5000"
                    autocomplete="off">
            </div>
          </div>

          <div class="grid2" style="margin-top:10px">
            <div class="field">
              <div class="label">PDF</div>
              <input type="file" name="pdf" accept="application/pdf">
            </div>
            <div class="field">
              <div class="label">XML</div>
              <input type="file" name="xml" accept=".xml,application/xml,text/xml">
            </div>
          </div>

          <div class="mut" style="margin-top:8px">
            Sube PDF/XML reales para que después el cliente pueda descargarlos desde su perfil.
          </div>

          <div class="p360-modal__foot">
            <button type="button" class="btn ghost js-close-modal">Cancelar</button>
            <button type="submit" class="btn primary">Adjuntar</button>
          </div>
        </form>
      </section>

      {{-- C) EMAIL READY --}}
      <section class="p360-modal__section" data-pane="m-email" hidden>
        <form method="POST"
              id="form-email"
              action=""
              data-action-template="{{ route('admin.billing.invoices.requests.email_ready', ['id' => '__ID__']) }}"
              novalidate>
          @csrf
          <input type="hidden" name="_p360_row_id" id="email-row-id" value="">

          <div class="field">
            <div class="label">Correo destino</div>
            <input name="to"
                  id="email-to"
                  placeholder="vacío = correo del cliente"
                  value=""
                  inputmode="email"
                  autocomplete="off"
                  spellcheck="false">
            <div class="mut" style="margin-top:6px">
              Si lo dejas vacío, se usa el email del cliente (accounts.email).
            </div>
          </div>

          <div class="p360-modal__foot">
            <button type="button" class="btn ghost js-close-modal">Cancelar</button>
            <button type="submit" class="btn primary">Enviar</button>
          </div>
        </form>
      </section>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';

  const modal = document.getElementById('p360-modal');
  if(!modal) return;

  const title = modal.querySelector('#p360-modal-title');
  const sub   = modal.querySelector('#p360-modal-sub');

  const panes = Array.from(modal.querySelectorAll('[data-pane]'));
  const openBtns  = Array.from(document.querySelectorAll('.js-open-modal'));
  const closeBtns = Array.from(modal.querySelectorAll('.js-close-modal'));

  const formEdit   = modal.querySelector('#form-edit');
  const formAttach = modal.querySelector('#form-attach');
  const formEmail  = modal.querySelector('#form-email');

  const editStatus = modal.querySelector('#edit-status');
  const editUuid   = modal.querySelector('#edit-uuid');
  const editNotes  = modal.querySelector('#edit-notes');

  const attachUuid  = modal.querySelector('#attach-uuid');
  const attachNotes = modal.querySelector('#attach-notes');

  const emailTo = modal.querySelector('#email-to');

  const editRowId   = modal.querySelector('#edit-row-id');
  const attachRowId = modal.querySelector('#attach-row-id');
  const emailRowId  = modal.querySelector('#email-row-id');

  function show(paneId){
    panes.forEach(p => p.hidden = (p.getAttribute('data-pane') !== paneId));
    modal.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('p360-modal-open');
    document.body.classList.add('p360-modal-open');
  }

  function hide(){
    modal.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('p360-modal-open');
    document.body.classList.remove('p360-modal-open');
    panes.forEach(p => p.hidden = true);
    modal.dataset.rowId = '';
  }

  closeBtns.forEach(b => b.addEventListener('click', hide));
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') hide(); });

  function withId(tpl, id){
    return String(tpl || '').replace('__ID__', String(id));
  }

  function setActionFromTemplate(form, id){
    if(!form) return false;
    const tpl = (form.getAttribute('data-action-template') || '').trim();
    if(!tpl) return false;

    const rowId = String(id || '').trim();
    if(!rowId) return false;

    const url = withId(tpl, rowId).trim();
    if(!url) return false;

    form.setAttribute('action', url);
    return true;
  }

  function isBadAction(act){
    const a = String(act || '').trim().toLowerCase();

    // vacíos o basura
    if(!a || a === '#' || a === 'javascript:void(0)' || a === 'javascript:void(0);') return true;

    // POST al listado (causa exacta del MethodNotAllowed)
    const isListPost =
      a.includes('/admin/billing/invoices/requests') &&
      !a.match(/\/admin\/billing\/invoices\/requests\/\d+\//);

    return !!isListPost;
  }

  function toast(msg){
    if (window.P360 && typeof window.P360.toast === 'function') window.P360.toast(msg);
    else alert(msg);
  }

  function guardFormAction(form, pane){
    if(!form) return;

    form.addEventListener('submit', (e)=>{
      // Si ya tiene action bueno, deja pasar
      const current = form.getAttribute('action');
      if(!isBadAction(current)) return;

      // Intento 1: usar id guardado en modal.dataset.rowId
      const id = String(modal.dataset.rowId || '').trim();
      const ok = setActionFromTemplate(form, id);

      // Re-evalúa
      const after = form.getAttribute('action');
      if(ok && !isBadAction(after)) return;

      // Si sigue mal, bloquea (evita POST al listado)
      e.preventDefault();
      toast('Ruta inválida (action). Abre el modal desde un botón de fila para asignar ID (y no enviar POST al listado).');
    });
  }

  openBtns.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const modalId = (btn.getAttribute('data-modal') || '').trim();
      const id      = (btn.getAttribute('data-id') || '').trim();
      const period  = btn.getAttribute('data-period') || '—';
      const account = btn.getAttribute('data-account') || '—';

      if(!modalId){
        toast('No se pudo abrir modal: falta data-modal.');
        return;
      }
      if(!id){
        toast('No se pudo abrir modal: falta data-id (row id).');
        return;
      }

      modal.dataset.rowId = id;

      // header
      if(modalId === 'm-edit')   title.textContent = 'Editar solicitud';
      if(modalId === 'm-attach') title.textContent = 'Adjuntar factura (PDF/XML)';
      if(modalId === 'm-email')  title.textContent = 'Enviar “Factura lista”';

      sub.textContent = 'Cuenta: ' + account + ' · Periodo: ' + period + ' · ID: ' + id;

      // panes + actions + values
      if(modalId === 'm-edit'){
        setActionFromTemplate(formEdit, id);
        if(editRowId) editRowId.value = id;

        const st = btn.getAttribute('data-status') || 'requested';
        if(editStatus) editStatus.value = st;

        if(editUuid)  editUuid.value  = btn.getAttribute('data-uuid')  || '';
        if(editNotes) editNotes.value = btn.getAttribute('data-notes') || '';
      }

      if(modalId === 'm-attach'){
        setActionFromTemplate(formAttach, id);
        if(attachRowId) attachRowId.value = id;

        if(attachUuid)  attachUuid.value  = btn.getAttribute('data-uuid') || '';
        if(attachNotes) attachNotes.value = '';
      }

      if(modalId === 'm-email'){
        setActionFromTemplate(formEmail, id);
        if(emailRowId) emailRowId.value = id;

        if(emailTo) emailTo.value = '';
      }

      show(modalId);
    });
  });

  guardFormAction(formEdit, 'm-edit');
  guardFormAction(formAttach, 'm-attach');
  guardFormAction(formEmail, 'm-email');
})();
</script>
@endpush