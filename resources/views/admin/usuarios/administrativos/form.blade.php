@extends('layouts.admin')
@section('title', $mode==='create' ? 'Nuevo admin' : 'Editar admin')
@section('pageClass','p360-admin-usuarios-admin')
@section('contentLayout','full')

@push('styles')
@php
  $CSS_ABS = public_path('assets/admin/css/usuarios-admin.css');
  $CSS_URL = asset('assets/admin/css/usuarios-admin.css') . (is_file($CSS_ABS) ? ('?v='.filemtime($CSS_ABS)) : '');
@endphp
<link rel="stylesheet" href="{{ $CSS_URL }}">
@endpush

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">PACTOPIA 360</div>
      <h1 class="p360-ph-title">
        {{ $mode==='create' ? 'Crear usuario administrativo' : 'Editar usuario administrativo' }}
      </h1>
      <div class="p360-ph-sub">Credenciales, rol, permisos y flags.</div>
    </div>
    <div class="p360-ph-right">
      <a class="btnx ghost" href="{{ route('admin.usuarios.administrativos.index') }}">← Volver</a>
    </div>
  </div>
@endsection

@section('content')
@php
  // ===== catálogo de permisos por módulos (alineado a sidebar) =====
  $permCatalog = [
    'Administración' => [
      'usuarios_admin.ver' => 'Ver usuarios administrativos',
      'usuarios_admin.crear' => 'Crear usuarios administrativos',
      'usuarios_admin.editar' => 'Editar usuarios administrativos',
      'usuarios_admin.eliminar' => 'Eliminar usuarios administrativos',
      'clientes.ver' => 'Ver clientes (accounts)',
      'clientes.editar' => 'Editar clientes',
    ],
    'Billing SaaS' => [
      'facturacion.ver' => 'Ver estados de cuenta / facturación',
      'pagos.ver' => 'Ver pagos',
      'reportes.ver' => 'Ver reportes (si aplica a billing)',
      'billing.*' => 'Acceso completo Billing (billing.*)',
      'sat.*' => 'Acceso completo SAT admin (sat.*)',
    ],
    'Auditoría & Configuración' => [
      'auditoria.ver' => 'Ver auditoría',
      'configuracion.ver' => 'Ver configuración',
      'perfiles.ver' => 'Ver perfiles/permisos (legacy)',
    ],
    'Reportes' => [
      'reportes.ver' => 'Ver reportes',
    ],
    'Soporte' => [
      'soporte.*' => 'Acceso soporte completo (soporte.*)',
    ],
    'Wildcard' => [
      '*' => 'Acceso total (*)',
    ],
  ];

  $permsText = old(
    'permisos_text',
    is_array($row->permisos ?? null)
      ? implode("\n", $row->permisos)
      : (string)($row->permisos ?? '')
  );

  $hasEstatus   = property_exists($row, 'estatus');
  $hasIsBlocked = property_exists($row, 'is_blocked');

  $estatusVal   = old('estatus', $hasEstatus ? (string)($row->estatus ?? 'activo') : 'activo');
  $blockedVal   = old('is_blocked', $hasIsBlocked ? (string)($row->is_blocked ?? 0) : '0');
@endphp

  <div class="p360-card p360-card-wide">

    @if(session('ok'))
      <div class="p360-flash ok">{{ session('ok') }}</div>
    @endif
    @if(session('err'))
      <div class="p360-flash err">{{ session('err') }}</div>
    @endif

    <form id="admForm" method="POST"
          action="{{ $mode==='create' ? route('admin.usuarios.administrativos.store') : route('admin.usuarios.administrativos.update', $row->id) }}">
      @csrf
      @if($mode==='edit') @method('PUT') @endif

      <div class="ua-grid">

        {{-- Columna izquierda --}}
        <div class="ua-panel">
          <div class="ua-head">
            <div class="ua-title">Datos del usuario</div>
            <div class="ua-sub">Identidad y estado de cuenta.</div>
          </div>

          <div class="ua-fields">
            <div class="field">
              <label>Nombre</label>
              <input name="nombre" value="{{ old('nombre', $row->nombre) }}" required>
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="field">
              <label>Email</label>
              <input name="email" type="email" value="{{ old('email', $row->email) }}" required>
              @error('email') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="field">
              <label>{{ $mode==='create' ? 'Password' : 'Password (opcional)' }}</label>

              <div class="ua-pw">
                <input id="admPassword" name="password" type="password" {{ $mode==='create' ? 'required' : '' }}
                      autocomplete="new-password"
                      placeholder="{{ $mode==='create' ? 'Genera una segura o escribe una…' : 'Dejar vacío para no cambiar' }}">

                <div class="ua-pw-tools">
                  <button class="btnx sm ghost" type="button" id="pwGen" title="Generar password segura">Generar</button>
                  <button class="btnx sm" type="button" id="pwShow" aria-pressed="false" title="Mostrar / ocultar">Mostrar</button>
                  <button class="btnx sm" type="button" id="pwCopy" title="Copiar al portapapeles">Copiar</button>
                </div>
              </div>

              <div class="muted">
                Mínimo 10 caracteres. Recomendado: 16+ con mayúsculas, minúsculas, números y símbolos.
              </div>
              <div class="muted ua-pw-hint" id="pwHint" style="display:none;">
                Generada y copiable. (Tip: se activó “Forzar cambio password = Sí”.)
              </div>

              @error('password') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- layout dinámico para rol/activo/estatus/superadmin --}}
            <div class="{{ ($hasEstatus || $hasIsBlocked) ? 'ua-row4' : 'ua-row3' }}">
              <div class="field sm">
                <label>Rol</label>
                <input name="rol" value="{{ old('rol', $row->rol) }}" placeholder="ej. admin, soporte, auditor…">
                <div class="muted">Máx 30 caracteres.</div>
                @error('rol') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div class="field sm">
                <label>Estado</label>
                <select name="activo" id="selActivo">
                  <option value="1" @selected((int)old('activo', (int)$row->activo)===1)>Activo</option>
                  <option value="0" @selected((int)old('activo', (int)$row->activo)===0)>Inactivo</option>
                </select>
                @error('activo') <div class="err">{{ $message }}</div> @enderror
              </div>

              @if($hasEstatus)
                <div class="field sm">
                  <label>Estatus (auth)</label>
                  <select name="estatus" id="selEstatus">
                    <option value="activo" @selected(strtolower($estatusVal)==='activo')>activo</option>
                    <option value="inactivo" @selected(strtolower($estatusVal)==='inactivo')>inactivo</option>
                    <option value="bloqueado" @selected(strtolower($estatusVal)==='bloqueado')>bloqueado</option>
                  </select>
                  <div class="muted">Este campo afecta el login (no debe quedar NULL).</div>
                  @error('estatus') <div class="err">{{ $message }}</div> @enderror
                </div>
              @endif

              <div class="field sm">
                <label>SuperAdmin</label>
                <select name="es_superadmin">
                  <option value="0" @selected((int)old('es_superadmin', (int)$row->es_superadmin)===0)>No</option>
                  <option value="1" @selected((int)old('es_superadmin', (int)$row->es_superadmin)===1)>Sí</option>
                </select>
                <div class="muted">Ignora permisos si es Sí.</div>
                @error('es_superadmin') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            @if($hasIsBlocked)
              <div class="field sm">
                <label>Bloqueado</label>
                <select name="is_blocked" id="selBlocked">
                  <option value="0" @selected((int)$blockedVal===0)>No</option>
                  <option value="1" @selected((int)$blockedVal===1)>Sí</option>
                </select>
                <div class="muted">Si es “Sí”, se fuerza estatus “bloqueado”.</div>
                @error('is_blocked') <div class="err">{{ $message }}</div> @enderror
              </div>
            @endif

            <div class="field sm">
              <label>Forzar cambio password</label>
              <select name="force_password_change">
                <option value="0" @selected((int)old('force_password_change', (int)$row->force_password_change)===0)>No</option>
                <option value="1" @selected((int)old('force_password_change', (int)$row->force_password_change)===1)>Sí</option>
              </select>
              @error('force_password_change') <div class="err">{{ $message }}</div> @enderror
            </div>

            @if($mode==='edit')
              <div class="ua-row2">
                <div class="field sm">
                  <label>Último login</label>
                  <input value="{{ $row->last_login_at ?: '—' }}" disabled>
                </div>
                <div class="field sm">
                  <label>IP</label>
                  <input value="{{ $row->last_login_ip ?: '—' }}" disabled>
                </div>
              </div>
            @endif
          </div>
        </div>

        {{-- Columna derecha --}}
        <div class="ua-panel">
          <div class="ua-head">
            <div class="ua-title">Permisos & Módulos</div>
            <div class="ua-sub">Controla qué ve en el sidebar y qué rutas puede abrir.</div>
          </div>

          <div class="ua-perm-tools">
            <input id="permSearch" class="ua-search" type="search" placeholder="Buscar permiso… (clientes, billing, reportes…)" autocomplete="off">
            <div class="ua-btns">
              <button class="btnx sm" type="button" id="permAll">Todo</button>
              <button class="btnx sm ghost" type="button" id="permNone">Nada</button>
              <button class="btnx sm warn" type="button" id="permBilling">Billing básico</button>
            </div>
          </div>

          <div class="ua-perm-box" id="permBox">
            @foreach($permCatalog as $group => $items)
              <div class="ua-pgroup" data-group="{{ strtolower($group) }}">
                <div class="ua-phead">
                  <div class="ua-ptitle">{{ $group }}</div>
                  <div class="ua-psub muted">{{ count($items) }} permisos</div>
                </div>

                <div class="ua-plist">
                  @foreach($items as $key => $label)
                    <label class="ua-pitem" data-key="{{ strtolower($key) }}" data-label="{{ strtolower($label) }}">
                      <input type="checkbox" class="permChk" value="{{ strtolower($key) }}">
                      <span class="ua-pkey mono">{{ $key }}</span>
                      <span class="ua-plabel">{{ $label }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>

          <div class="ua-custom">
            <div class="ua-custom-head">
              <div class="ua-ctitle">Permisos personalizados</div>
              <div class="muted">Se guardan como JSON en <span class="mono">permisos</span>.</div>
            </div>

            <textarea id="permisos_text" name="permisos_text" rows="7"
              placeholder="clientes.ver&#10;billing.*&#10;reportes.ver">{{ $permsText }}</textarea>
            @error('permisos_text') <div class="err">{{ $message }}</div> @enderror

            <div class="ua-hint">
              Tip: usa wildcards <span class="mono">billing.*</span> o <span class="mono">*</span>.
            </div>
          </div>
        </div>

      </div>

      <div class="ua-actions">
        <button class="btnx primary" type="submit">{{ $mode==='create' ? 'Crear usuario' : 'Guardar cambios' }}</button>
        <a class="btnx ghost" href="{{ route('admin.usuarios.administrativos.index') }}">Cancelar</a>
      </div>
    </form>

    @if($mode==='edit')
      <hr class="ua-sep">

      <div class="ua-reset">
        <div>
          <div class="ua-title">Reset password</div>
          <div class="muted">Si lo dejas vacío, se genera una aleatoria y se marca <span class="mono">force_password_change=1</span>.</div>
        </div>

        <form method="POST" action="{{ route('admin.usuarios.administrativos.reset_password', $row->id) }}" class="ua-reset-form">
          @csrf
          <input type="text" name="new_password" placeholder="Nueva contraseña (opcional)">
          <button class="btnx" type="submit">Reset</button>
        </form>
      </div>

      @error('new_password') <div class="err">{{ $message }}</div> @enderror
    @endif

  </div>

  <style>
    .p360-flash{margin:0 0 12px;padding:10px 12px;border-radius:12px;font-weight:700}
    .p360-flash.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .p360-flash.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

    .ua-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:14px}
    @media (max-width:1024px){.ua-grid{grid-template-columns:1fr}}

    .ua-panel{border:1px solid rgba(15,23,42,.10);border-radius:16px;background:#fff;overflow:hidden}
    html.theme-dark .ua-panel{background:rgba(17,24,39,.55);border-color:rgba(255,255,255,.12)}
    .ua-head{padding:14px 14px 10px;border-bottom:1px solid rgba(15,23,42,.08)}
    html.theme-dark .ua-head{border-bottom-color:rgba(255,255,255,.10)}
    .ua-title{font:900 16px/1.15 system-ui;margin:0}
    .ua-sub{color:#64748b;font:650 12px/1.2 system-ui;margin-top:4px}
    html.theme-dark .ua-sub{color:rgba(255,255,255,.60)}
    .ua-fields{padding:14px}
    .ua-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    .ua-row4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px}
    .ua-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    @media (max-width:1100px){.ua-row4{grid-template-columns:1fr 1fr}}
    @media (max-width:900px){.ua-row3{grid-template-columns:1fr}.ua-row4{grid-template-columns:1fr}.ua-row2{grid-template-columns:1fr}}

    .field label{display:block;font:800 12px/1 system-ui;color:#0f172a;margin:0 0 6px}
    html.theme-dark .field label{color:#e5e7eb}
    .field input,.field select,.ua-custom textarea,.ua-search{
      width:100%;
      border:1px solid rgba(15,23,42,.12);
      border-radius:12px;
      padding:10px 12px;
      background:#fff;
      color:#0f172a;
      outline:none;
    }
    html.theme-dark .field input,html.theme-dark .field select,html.theme-dark .ua-custom textarea,html.theme-dark .ua-search{
      background:rgba(2,6,23,.35);
      border-color:rgba(255,255,255,.14);
      color:#e5e7eb;
    }
    .field input:focus,.field select:focus,.ua-custom textarea:focus,.ua-search:focus{box-shadow:0 0 0 3px rgba(99,102,241,.18)}
    .err{margin-top:6px;color:#b91c1c;font-weight:700}
    html.theme-dark .err{color:#fecaca}
    .muted{color:#64748b;font:650 12px/1.2 system-ui;margin-top:6px}
    html.theme-dark .muted{color:rgba(255,255,255,.60)}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}

    .ua-perm-tools{padding:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .ua-search{flex:1 1 260px}
    .ua-btns{display:flex;gap:8px;flex-wrap:wrap}
    .ua-perm-box{padding:0 14px 14px;max-height:420px;overflow:auto}
    .ua-pgroup{border:1px solid rgba(15,23,42,.08);border-radius:14px;margin:10px 0;background:rgba(15,23,42,.02)}
    html.theme-dark .ua-pgroup{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.10)}
    .ua-phead{padding:10px 12px;border-bottom:1px solid rgba(15,23,42,.08);display:flex;justify-content:space-between;align-items:center}
    html.theme-dark .ua-phead{border-bottom-color:rgba(255,255,255,.10)}
    .ua-ptitle{font:900 13px/1 system-ui}
    .ua-plist{padding:8px 10px;display:grid;gap:6px}
    .ua-pitem{display:grid;grid-template-columns:18px 1fr;gap:10px;align-items:start;padding:8px 10px;border-radius:12px;cursor:pointer}
    .ua-pitem:hover{background:rgba(15,23,42,.05)}
    html.theme-dark .ua-pitem:hover{background:rgba(255,255,255,.06)}
    .ua-pkey{font:900 12px/1.2 ui-monospace}
    .ua-plabel{color:#334155;font:650 12px/1.2 system-ui}
    html.theme-dark .ua-plabel{color:rgba(255,255,255,.72)}

    .ua-custom{padding:12px 14px 14px;border-top:1px solid rgba(15,23,42,.08)}
    html.theme-dark .ua-custom{border-top-color:rgba(255,255,255,.10)}
    .ua-custom-head{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:8px}
    .ua-ctitle{font:900 13px/1 system-ui}
    .ua-hint{margin-top:8px;color:#64748b;font:650 12px/1.2 system-ui}
    html.theme-dark .ua-hint{color:rgba(255,255,255,.60)}

    .ua-actions{margin-top:14px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
    .ua-sep{border:0;border-top:1px solid rgba(15,23,42,.10);margin:14px 0}
    html.theme-dark .ua-sep{border-top-color:rgba(255,255,255,.12)}
    .ua-reset{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .ua-reset-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .ua-reset-form input{min-width:260px}
  </style>

  <script>
  (function(){
    'use strict';

    const ta = document.getElementById('permisos_text');
    const checks = Array.from(document.querySelectorAll('.permChk'));
    const search = document.getElementById('permSearch');

    // ======================================================
    // Password generator (seguro) + mostrar + copiar
    // ======================================================
    const pwInput = document.getElementById('admPassword');
    const pwGen   = document.getElementById('pwGen');
    const pwShow  = document.getElementById('pwShow');
    const pwCopy  = document.getElementById('pwCopy');
    const pwHint  = document.getElementById('pwHint');
    const forceSel = document.querySelector('select[name="force_password_change"]');

    function randInt(max){
      const a = new Uint32Array(1);
      window.crypto.getRandomValues(a);
      return a[0] % max;
    }
    function pick(chars){ return chars[randInt(chars.length)]; }
    function shuffle(arr){
      for(let i = arr.length - 1; i > 0; i--){
        const j = randInt(i + 1);
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
      return arr;
    }
    function generateSecurePassword(len = 16){
      const lowers = 'abcdefghjkmnpqrstuvwxyz';
      const uppers = 'ABCDEFGHJKMNPQRSTUVWXYZ';
      const nums   = '23456789';
      const syms   = '!@#$%^&*()-_=+[]{};:,.?';

      const all = lowers + uppers + nums + syms;
      const out = [];
      out.push(pick(lowers));
      out.push(pick(uppers));
      out.push(pick(nums));
      out.push(pick(syms));
      while(out.length < Math.max(10, len)){
        out.push(pick(all));
      }
      return shuffle(out).join('');
    }
    async function copyToClipboard(text){
      try{ await navigator.clipboard.writeText(text); return true; }
      catch(e){
        try{
          const t = document.createElement('textarea');
          t.value = text;
          t.setAttribute('readonly','');
          t.style.position = 'fixed';
          t.style.left = '-9999px';
          document.body.appendChild(t);
          t.select();
          const ok = document.execCommand('copy');
          document.body.removeChild(t);
          return !!ok;
        }catch(_){ return false; }
      }
    }
    function showHint(msg){
      if(!pwHint) return;
      pwHint.textContent = msg;
      pwHint.style.display = '';
      setTimeout(()=>{ try{ pwHint.style.display='none'; }catch(_){} }, 4500);
    }

    pwGen?.addEventListener('click', async ()=>{
      if(!pwInput) return;
      const pass = generateSecurePassword(16);
      pwInput.value = pass;
      if(forceSel) forceSel.value = '1';
      const ok = await copyToClipboard(pass);
      showHint(ok
        ? 'Password generada y copiada. (Forzar cambio password = Sí)'
        : 'Password generada. No se pudo copiar automáticamente (cópiala manualmente).'
      );
      pwInput.focus();
      pwInput.select?.();
    });

    pwShow?.addEventListener('click', ()=>{
      if(!pwInput || !pwShow) return;
      const isPwd = pwInput.getAttribute('type') === 'password';
      pwInput.setAttribute('type', isPwd ? 'text' : 'password');
      pwShow.setAttribute('aria-pressed', isPwd ? 'true' : 'false');
      pwShow.textContent = isPwd ? 'Ocultar' : 'Mostrar';
    });

    pwCopy?.addEventListener('click', async ()=>{
      if(!pwInput) return;
      const val = (pwInput.value || '').trim();
      if(!val){
        showHint('No hay password para copiar.');
        pwInput.focus();
        return;
      }
      const ok = await copyToClipboard(val);
      showHint(ok ? 'Copiada al portapapeles.' : 'No se pudo copiar. Copia manualmente.');
    });

    // ======================================================
    // Permisos: textarea <-> checks
    // ======================================================
    function normalizeListFromTextarea(){
      const t = (ta.value || '').trim().replace(/\r\n/g,'\n').replace(/\r/g,'\n');
      if(!t) return new Set();
      const parts = t.split(/[\n,]+/).map(s => s.trim().toLowerCase()).filter(Boolean);
      return new Set(parts);
    }
    function writeTextareaFromSet(set){
      const arr = Array.from(set).filter(Boolean).sort();
      ta.value = arr.join("\n");
    }
    function syncChecksFromTextarea(){
      const set = normalizeListFromTextarea();
      checks.forEach(ch => { ch.checked = set.has(ch.value); });
    }
    function syncTextareaFromChecks(){
      const set = normalizeListFromTextarea();
      checks.forEach(ch => {
        if(ch.checked) set.add(ch.value);
        else set.delete(ch.value);
      });
      writeTextareaFromSet(set);
    }

    syncChecksFromTextarea();
    checks.forEach(ch => ch.addEventListener('change', syncTextareaFromChecks));
    ta.addEventListener('input', syncChecksFromTextarea);

    document.getElementById('permAll')?.addEventListener('click', ()=>{
      checks.forEach(ch => ch.checked = true);
      syncTextareaFromChecks();
    });
    document.getElementById('permNone')?.addEventListener('click', ()=>{
      checks.forEach(ch => ch.checked = false);
      syncTextareaFromChecks();
    });
    document.getElementById('permBilling')?.addEventListener('click', ()=>{
      const allow = new Set(['facturacion.ver','pagos.ver','billing.*','sat.*']);
      checks.forEach(ch => ch.checked = allow.has(ch.value));
      syncTextareaFromChecks();
    });

    function applySearch(q){
      q = String(q||'').trim().toLowerCase();
      document.querySelectorAll('.ua-pitem').forEach(row=>{
        const k = row.getAttribute('data-key') || '';
        const l = row.getAttribute('data-label') || '';
        const ok = (!q) || k.includes(q) || l.includes(q);
        row.style.display = ok ? '' : 'none';
      });
      document.querySelectorAll('.ua-pgroup').forEach(g=>{
        const any = Array.from(g.querySelectorAll('.ua-pitem')).some(x => x.style.display !== 'none');
        g.style.display = any ? '' : 'none';
      });
    }
    search?.addEventListener('input', e => applySearch(e.target.value));

    // ======================================================
    // Coherencia: activo / estatus / is_blocked
    // ======================================================
    const selActivo  = document.getElementById('selActivo');
    const selEstatus = document.getElementById('selEstatus');
    const selBlocked = document.getElementById('selBlocked');

    function syncStatus(){
      const activo  = selActivo ? String(selActivo.value) : '1';
      const blocked = selBlocked ? String(selBlocked.value) : '0';

      if (selEstatus) {
        if (blocked === '1') {
          selEstatus.value = 'bloqueado';
          if (selActivo) selActivo.value = '0';
          return;
        }
        if (activo === '0') {
          if (selEstatus.value === 'activo') selEstatus.value = 'inactivo';
          return;
        }
        // activo=1 y no bloqueado -> si estaba inactivo/bloqueado, regresa a activo
        if (selEstatus.value !== 'activo') selEstatus.value = 'activo';
      }
    }

    selActivo?.addEventListener('change', syncStatus);
    selBlocked?.addEventListener('change', syncStatus);
    selEstatus?.addEventListener('change', ()=>{
      // si eligen bloqueado, forzamos blocked=1 y activo=0
      if(!selEstatus) return;
      if (selEstatus.value === 'bloqueado') {
        if (selBlocked) selBlocked.value = '1';
        if (selActivo) selActivo.value = '0';
      }
      if (selEstatus.value === 'activo') {
        if (selBlocked) selBlocked.value = '0';
        if (selActivo) selActivo.value = '1';
      }
      if (selEstatus.value === 'inactivo') {
        if (selBlocked) selBlocked.value = '0';
        if (selActivo) selActivo.value = '0';
      }
    });

    // init
    syncStatus();
  })();
  </script>
@endsection
