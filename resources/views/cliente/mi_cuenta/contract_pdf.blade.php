{{-- resources/views/cliente/mi_cuenta/contract_pdf.blade.php --}}
@php
  $razon = $cuenta?->razon_social ?? $cuenta?->nombre ?? '—';
  $accountId = (string)($cuenta?->id ?? '');
  $signedAt = $contract->signed_at ? $contract->signed_at->format('Y-m-d H:i') : '—';
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Contrato firmado</title>
  <style>
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#0f172a; }
    .box{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
    h1{ font-size:16px; margin:0 0 8px; }
    h2{ font-size:13px; margin:14px 0 6px; }
    p{ margin:0 0 8px; line-height:1.5; }
    .mut{ color:#64748b; }
    .sig{ margin-top:12px; }
    .sig img{ max-width: 420px; width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:6px; background:#fff; }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>{{ $contract->title }} ({{ $contract->version }})</h1>
    <p><strong>Cuenta:</strong> {{ $razon }} <span class="mut">({{ $accountId }})</span></p>
    <p><strong>Firmado:</strong> {{ $signedAt }}</p>
    <p><strong>Correo:</strong> {{ $contract->signed_email ?? '—' }}</p>

    <h2>Objeto</h2>
    <p>El presente contrato regula la aceptación de los términos de uso del servicio Pactopia360 ERP conforme al plan contratado.</p>

    <h2>Aceptación</h2>
    <p>La firma constituye evidencia digital de aceptación, incluyendo fecha/hora, usuario firmante y hash.</p>

    <div class="sig">
      <p><strong>Firma:</strong></p>
      @if($contract->signature_png_base64)
        <img src="{{ $contract->signature_png_base64 }}" alt="Firma">
      @else
        <p class="mut">No disponible</p>
      @endif
      <p class="mono">Hash: {{ $contract->signature_hash ?? '—' }}</p>
    </div>
  </div>
</body>
</html>
