@extends('layouts.guest')
@section('title','DEV · QA')

@push('styles')
<style>
  .qa-wrap{width:min(1200px,96vw);margin:20px auto;font:14px system-ui,Segoe UI,Roboto}
  .qa-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .ok{background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;border-radius:10px;padding:8px 10px;margin:10px 0}
  .err{background:#7f1d1d;color:#fff;border:1px solid #991b1b;border-radius:10px;padding:8px 10px;margin:10px 0}
  table{width:100%;border-collapse:separate;border-spacing:0 6px}
  th,td{padding:8px 10px;background:#0f172a10;border:1px solid #e5e7eb}
  th{background:#0f172a0f;font-weight:800}
  .row-actions form{display:inline}
  .row-actions button{padding:6px 8px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;margin-right:6px}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:800;font-size:12px}
  .bg-ok{background:#10b98120;color:#065f46;border:1px solid #10b98155}
  .bg-pend{background:#64748b1a;color:#334155;border:1px solid #94a3b855}
  .small{font-size:12px;opacity:.85}
  .mt{margin-top:12px}
</style>
@endpush

@section('content')
<div class="qa-wrap">
  <div class="qa-head">
    <h1 style="margin:0">Panel QA (solo local/superadmin)</h1>
    <form method="GET">
      <input type="text" name="rfc"   placeholder="RFC"   value="{{ request('rfc') }}">
      <input type="text" name="email" placeholder="Email" value="{{ request('email') }}">
      <button>Filtrar</button>
    </form>
  </div>

  @if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif

  <h2>Cuentas recientes</h2>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>RFC</th><th>Nombre</th><th>Email</th><th>Teléfono</th>
          <th>Plan</th><th>Estado</th><th>Verifs</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      @foreach($accounts as $a)
        @php
          $toks = $tokens[$a->id] ?? collect();
          $ots  = $otps[$a->id] ?? collect();
        @endphp
        <tr>
          <td>{{ $a->id }}</td>
          <td>{{ $a->rfc }}</td>
          <td>{{ $a->display_name }}</td>
          <td>{{ $a->email }}</td>
          <td>{{ $a->phone }}</td>
          <td>{{ $a->plan_actual ?? $a->plan }}</td>
          <td>{{ $a->estado_cuenta }}</td>
          <td>
            <span class="badge {{ $a->email_verified_at ? 'bg-ok':'bg-pend' }}">Email {{ $a->email_verified_at? '✔':'—' }}</span>
            <span class="badge {{ $a->phone_verified_at ? 'bg-ok':'bg-pend' }}">Tel {{ $a->phone_verified_at? '✔':'—' }}</span>
          </td>
          <td class="row-actions">
            {{-- Reenviar email --}}
            <form method="POST" action="{{ route('admin.dev.resend_email') }}">@csrf
              <input type="hidden" name="account_id" value="{{ $a->id }}">
              <input type="hidden" name="email" value="{{ $a->email }}">
              <button>Reenviar email</button>
            </form>
            {{-- Forzar email verificado --}}
            <form method="POST" action="{{ route('admin.dev.force_email') }}">@csrf
              <input type="hidden" name="account_id" value="{{ $a->id }}">
              <button>Email ✔</button>
            </form>

            {{-- Generar OTP (con opción de editar teléfono) --}}
            <form method="POST" action="{{ route('admin.dev.send_otp') }}">@csrf
              <input type="hidden" name="account_id" value="{{ $a->id }}">
              <input type="text"   name="phone" value="{{ $a->phone }}" placeholder="+52 55 1234 5678" style="width:160px">
              <select name="channel">
                <option value="sms">SMS</option>
                <option value="whatsapp">WhatsApp</option>
              </select>
              <label class="small"><input type="checkbox" name="update_account_phone" value="1"> actualizar en cuenta</label>
              <button>OTP</button>
            </form>

            {{-- Forzar teléfono verificado + finalizar activación --}}
            <form method="POST" action="{{ route('admin.dev.force_phone') }}">@csrf
              <input type="hidden" name="account_id" value="{{ $a->id }}">
              <button>Teléfono ✔</button>
            </form>
          </td>
        </tr>

        @if($toks->count() || $ots->count())
          <tr>
            <td colspan="9">
              @if($toks->count())
                <div class="small"><strong>Tokens email</strong></div>
                <ul class="small">
                  @foreach($toks as $t)
                    <li>
                      {{ $t->email }} · expira {{ \Carbon\Carbon::parse($t->expires_at)->diffForHumans() }}
                      · <a target="_blank" href="{{ route('cliente.verify.email.token',['token'=>$t->token]) }}">abrir verificación</a>
                    </li>
                  @endforeach
                </ul>
              @endif
              @if($ots->count())
                <div class="small"><strong>OTPs recientes</strong></div>
                <ul class="small">
                  @foreach($ots as $o)
                    <li>#{{ $o->id }} · {{ $o->phone }} · {{ strtoupper($o->channel) }} · código <code>{{ $o->code }}</code>
                      · vence {{ \Carbon\Carbon::parse($o->expires_at)->diffForHumans() }}
                      {!! $o->used_at ? ' · <span style="color:#065f46">USADO</span>' : '' !!}
                    </li>
                  @endforeach
                </ul>
              @endif
            </td>
          </tr>
        @endif
      @endforeach
      </tbody>
    </table>
  </div>

  <h2 class="mt">Owners (últimos)</h2>
  <ul class="small">
    @foreach($owners as $u)
      <li>{{ $u->nombre }} — {{ $u->email }} — RFC {{ $u->rfc_padre }} — Estado: {{ $u->estado_cuenta }} — Plan: {{ $u->plan_actual }} — Activo: {{ $u->activo ? '✔':'—' }}</li>
    @endforeach
  </ul>
</div>
@endsection
