{{-- resources/views/emails/cliente/verify_email_text.blade.php --}}
@php
  $producto  = $producto ?? 'Pactopia360';
  $nombre    = $nombre ?? 'Usuario';
  $soporte   = $soporte ?? 'soporte@pactopia.com';

  // Si el controller pasa actionUrl, úsalo. Si no, fallback seguro al login.
  $loginUrl  = $loginUrl ?? (\Illuminate\Support\Facades\Route::has('cliente.login')
              ? route('cliente.login')
              : url('/cliente/login'));

  $actionUrl = $actionUrl ?? $loginUrl;

  $expiresHours = (int)($expiresHours ?? 24);
  $tz           = $tz ?? 'America/Mexico_City';
  $expiraEn     = \Illuminate\Support\Carbon::now($tz)->addHours($expiresHours)->format('Y-m-d H:i T');
@endphp


Confirma tu correo para activar tu cuenta en {{ $producto }}.

Hola {{ $nombre }},

👋 ¡Bienvenido(a) a {{ $producto }}!

Para activar tu cuenta, confirma tu correo haciendo clic en el siguiente enlace (válido por {{ $expiresHours }} horas, hasta {{ $expiraEn }}):

{{ $actionUrl }}

Si el enlace no se abre al hacer clic, cópialo y pégalo en la barra de tu navegador.

Después de confirmar tu correo, te pediremos verificar tu teléfono con un código de 6 dígitos para completar la seguridad de tu cuenta.

Si no iniciaste este registro, ignora este mensaje y no se activará ninguna cuenta.

—
Equipo {{ $producto }}
Soporte: {{ $soporte }}
