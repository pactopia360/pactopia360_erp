{{-- resources/views/legal/terminos.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Términos y condiciones · Pactopia360</title>
  <meta name="robots" content="noindex,nofollow">

  <style>
    :root{
      --ink:#0f172a; --mut:#64748b; --line:#e5e7eb; --bg:#f8fafc; --card:#ffffff;
      --shadow: 0 10px 30px rgba(15,23,42,.08);
      --radius: 18px;
      --pri:#7c3aed;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: radial-gradient(1200px 600px at 20% -10%, rgba(124,58,237,.12), transparent 60%),
                  radial-gradient(1000px 500px at 90% 0%, rgba(34,197,94,.10), transparent 60%),
                  var(--bg);
      color: var(--ink);
    }
    .wrap{max-width: 980px; margin: 34px auto; padding: 0 16px;}
    .topbar{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px;}
    .brand{display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;}
    .logo{
      width: 38px; height: 38px; border-radius: 12px;
      background: linear-gradient(135deg, rgba(124,58,237,1), rgba(34,197,94,1));
      box-shadow: 0 10px 25px rgba(124,58,237,.18);
    }
    .brand strong{font-weight:900; letter-spacing:.2px}
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      height: 40px; padding: 0 14px; border-radius: 12px;
      border: 1px solid var(--line);
      background: var(--card);
      color: var(--ink);
      text-decoration:none;
      font-weight: 800;
    }
    .btn:hover{background: rgba(2,6,23,.03);}
    .card{
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 22px;
    }
    h1{margin:0 0 6px; font-size: 22px; font-weight: 950;}
    .sub{margin:0 0 18px; color: var(--mut);}
    .content{line-height: 1.75; font-size: 14.5px;}
    .content h3{margin: 18px 0 8px; font-size: 16px;}
    .content ul{margin: 8px 0 0 18px;}
    .actions{display:flex; gap:10px; margin-top: 18px; flex-wrap: wrap;}
    .btn-primary{
      border-color: rgba(124,58,237,.25);
      background: rgba(124,58,237,.08);
      color: #4c1d95;
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="topbar">
      <a class="brand" href="/">
        <span class="logo" aria-hidden="true"></span>
        <div>
          <strong>Pactopia360</strong>
          <div style="font-size:12px; color:var(--mut)">Legal</div>
        </div>
      </a>

      <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
        <a class="btn" href="{{ url('/cliente/registro') }}">Ir a registro</a>
        <a class="btn btn-primary" href="{{ url('/cliente/login') }}">Iniciar sesión</a>
      </div>
    </div>

    <div class="card">
      <h1>Términos y condiciones</h1>
      <p class="sub">Última actualización: {{ now()->format('d/m/Y') }}</p>

      <div class="content">
        <p>
          Este documento describe los términos y condiciones de uso de la plataforma Pactopia360.
          Al registrarte y/o utilizar el servicio, aceptas estos términos.
        </p>

        <h3>1. Uso del servicio</h3>
        <ul>
          <li>El acceso y uso está sujeto al plan contratado (Free / Premium).</li>
          <li>El usuario es responsable de la veracidad de la información registrada.</li>
        </ul>

        <h3>2. Pagos, renovaciones y bloqueo</h3>
        <ul>
          <li>Las suscripciones Premium pueden ser mensuales o anuales, según la configuración vigente.</li>
          <li>En caso de falta de pago, la cuenta puede ser restringida conforme a la política de la plataforma.</li>
        </ul>

        <h3>3. Soporte</h3>
        <ul>
          <li>El soporte puede variar de acuerdo con el plan.</li>
        </ul>

        <h3>4. Modificaciones</h3>
        <p>
          Pactopia360 puede actualizar estos términos cuando sea necesario. La versión vigente estará publicada en esta página.
        </p>
      </div>

      <div class="actions">
        <a class="btn" href="{{ url()->previous() }}">Volver</a>
        <a class="btn btn-primary" href="{{ url('/cliente/registro') }}">Continuar con registro</a>
      </div>
    </div>
  </div>
</body>
</html>
