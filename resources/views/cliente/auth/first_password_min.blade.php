<!doctype html>
<html lang="es"><meta charset="utf-8"><title>Test</title>
<body style="font-family:system-ui;padding:24px">
  <h1>Vista mínima OK</h1>
  <p>Si ves esto, el problema no es el enrutado ni el middleware, era el Blade original.</p>
  <form method="POST" action="{{ route('cliente.password.first.store') }}">
    @csrf
    <input type="password" name="password" placeholder="Nueva contraseña" required>
    <input type="password" name="password_confirmation" placeholder="Confirmar" required>
    <button type="submit">Guardar</button>
  </form>
</body>
</html>
