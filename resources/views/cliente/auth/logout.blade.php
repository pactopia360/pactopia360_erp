{{-- resources/views/cliente/auth/logout.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Cerrando sesión…</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;background:#0b1220;color:#e5e7eb}
    .box{max-width:520px;padding:24px 20px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);box-shadow:0 12px 30px rgba(0,0,0,.35)}
    .muted{opacity:.8;font-size:14px;margin-top:8px}
  </style>
</head>
<body>
  <div class="box">
    <div><strong>Cerrando sesión…</strong></div>
    <div class="muted">Si no redirige, da clic en “Continuar”.</div>

    <form id="logoutForm" method="POST" action="{{ route('cliente.logout') }}" style="margin-top:14px">
      @csrf
      <button type="submit" style="padding:10px 14px;border-radius:10px;border:0;background:#2563eb;color:#fff;cursor:pointer">
        Continuar
      </button>
    </form>
  </div>

  <script>
    (function () {
      var f = document.getElementById('logoutForm');
      if (f) f.submit();
    })();
  </script>
</body>
</html>
