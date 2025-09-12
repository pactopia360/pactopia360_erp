{{-- resources/views/admin/generic/placeholder.blade.php --}}
@php
  // Detecta layout disponible para evitar "No se encontró la vista [layouts.app]"
  $candidates = [
    'layouts.app',
    'admin.layouts.app',
    'layouts.admin',
    'layouts.master',
    'layouts.main',
    'admin.layout',
  ];
  $layout = null;
  foreach ($candidates as $c) { if (view()->exists($c)) { $layout = $c; break; } }
@endphp

@if($layout)
  @extends($layout)
  @section('title', ($title ?? 'Módulo') . ' | ' . ($company ?? 'PACTOPIA 360'))
  @section('content')
    @include('admin.generic._placeholder_body', ['title' => $title ?? 'Módulo', 'company' => $company ?? 'PACTOPIA 360'])
  @endsection
@else
  {{-- Fallback HTML completo si no hay layout (no rompe navegación) --}}
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <title>{{ ($title ?? 'Módulo') . ' | ' . ($company ?? 'PACTOPIA 360') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;margin:0;background:#0f172a;color:#e2e8f0}
      .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
      .card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.15);border-radius:14px;padding:18px}
      .t{font-size:22px;font-weight:800;margin:0 0 8px}
      .muted{color:#94a3b8}
      .btn{display:inline-flex;gap:8px;align-items:center;padding:8px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.25);text-decoration:none;color:#fff;margin-top:10px}
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="card">
        <div class="t">{{ $title ?? 'Módulo' }}</div>
        <div class="muted">Empresa: {{ $company ?? 'PACTOPIA 360' }}</div>
        <p>Este módulo está en preparación. La ruta es estable y el menú ya funciona.</p>
        <a class="btn" href="{{ route('admin.home') }}">← Volver al Home</a>
      </div>
    </div>
  </body>
  </html>
@endif
