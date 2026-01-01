@extends('layouts.admin')

@section('title','Crear cliente manual')
@section('layout','full')
@section('pageClass','p360-admin-manual-account')

@push('styles')
<style>
  .ma-wrap{ max-width: 980px; margin: 0 auto; padding: 18px; }
  .ma-card{ background:#fff;border:1px solid rgba(148,163,184,.25);border-radius:16px;box-shadow:0 10px 25px rgba(15,23,42,.06); }
  .ma-head{ padding:16px 18px;border-bottom:1px solid rgba(148,163,184,.25); }
  .ma-title{ font-weight:800;font-size:18px; }
  .ma-sub{ color:#64748b;font-size:13px;margin-top:4px; }
  .ma-body{ padding:18px; display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .ma-ctl{ display:flex; flex-direction:column; gap:6px; }
  .ma-ctl label{ font-size:12px; color:#475569; font-weight:700; }
  .ma-ctl .in, .ma-ctl textarea, .ma-ctl select{
    height:42px; border-radius:12px; border:1px solid rgba(148,163,184,.35);
    padding:0 12px; outline:none; background:#fff;
  }
  .ma-ctl textarea{ height:110px; padding:10px 12px; }
  .ma-foot{ padding:16px 18px;border-top:1px solid rgba(148,163,184,.25); display:flex; justify-content:flex-end; gap:10px; }
  .ma-full{ grid-column:1 / -1; }
  .btn{ border-radius:12px; padding:10px 14px; border:1px solid rgba(148,163,184,.35); background:#fff; cursor:pointer; }
  .btn-dark{ background:#0b1220;color:#fff;border-color:#0b1220; }
  .msg{ margin:10px 0; padding:10px 12px; border-radius:12px; }
  .msg.err{ background:#fff1f2; border:1px solid rgba(244,63,94,.35); color:#9f1239; }
  .msg.ok{ background:#ecfdf5; border:1px solid rgba(34,197,94,.35); color:#065f46; }
</style>
@endpush

@section('content')
<div class="ma-wrap">
  <div class="ma-card">
    <div class="ma-head">
      <div class="ma-title">Crear cliente manual</div>
      <div class="ma-sub">Crea la cuenta, asigna precio personalizado, crea usuario y envía acceso + estado de cuenta.</div>

      @if($errors->any())
        <div class="msg err">
          {{ implode(' · ', $errors->all()) }}
        </div>
      @endif
      @if(session('ok'))
        <div class="msg ok">{{ session('ok') }}</div>
      @endif
    </div>

    <form method="POST" action="{{ route('admin.accounts.manual.store') }}">
      @csrf
      <div class="ma-body">

        <div class="ma-ctl">
          <label>Email principal (login + to)</label>
          <input class="in" name="email" type="email" value="{{ old('email') }}" required>
        </div>

        <div class="ma-ctl">
          <label>Nombre</label>
          <input class="in" name="name" type="text" value="{{ old('name') }}">
        </div>

        <div class="ma-ctl">
          <label>Razón social</label>
          <input class="in" name="razon_social" type="text" value="{{ old('razon_social') }}">
        </div>

        <div class="ma-ctl">
          <label>RFC</label>
          <input class="in" name="rfc" type="text" value="{{ old('rfc') }}">
        </div>

        <div class="ma-ctl">
          <label>Plan</label>
          <select name="plan" class="in">
            <option value="PRO" @selected(old('plan','PRO')==='PRO')>PRO</option>
            <option value="FREE" @selected(old('plan')==='FREE')>FREE</option>
          </select>
        </div>

        <div class="ma-ctl">
          <label>Precio personalizado (MXN)</label>
          <input class="in" name="custom_amount" type="number" step="0.01" value="{{ old('custom_amount','0') }}" required>
        </div>

        <div class="ma-ctl">
          <label>Periodo (YYYY-MM)</label>
          <input class="in" name="period" type="text" value="{{ old('period', $period ?? now()->format('Y-m')) }}">
        </div>

        <div class="ma-ctl">
          <label>Enviar correo al crear</label>
          <select name="send_now" class="in">
            <option value="1" @selected(old('send_now','1')==='1')>Sí (URGENTE)</option>
            <option value="0" @selected(old('send_now')==='0')>No</option>
          </select>
        </div>

        <div class="ma-ctl ma-full">
          <label>Destinatarios adicionales TO (emails separados por coma o salto de línea)</label>
          <textarea name="recipients_to">{{ old('recipients_to') }}</textarea>
        </div>

        <div class="ma-ctl ma-full">
          <label>CC</label>
          <textarea name="recipients_cc">{{ old('recipients_cc') }}</textarea>
        </div>

        <div class="ma-ctl ma-full">
          <label>BCC</label>
          <textarea name="recipients_bcc">{{ old('recipients_bcc') }}</textarea>
        </div>

      </div>

      <div class="ma-foot">
        <a href="{{ route('admin.billing.statements.index') }}" class="btn">Cancelar</a>
        <button class="btn btn-dark" type="submit">Crear y enviar</button>
      </div>
    </form>
  </div>
</div>
@endsection
