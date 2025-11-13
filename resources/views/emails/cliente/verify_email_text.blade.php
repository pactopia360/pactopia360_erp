{{-- resources/views/emails/cliente/verify_email_text.blade.php --}}
@php
  use Carbon\Carbon;

  $producto     = $producto     ?? 'Pactopia360';
  $nombre       = $nombre       ?? 'Usuario';
  $actionUrl    = $actionUrl    ?? ( \Illuminate\Support\Facades\Route::has('cliente.verificar.email')
                    ? route('cliente.verificar.email')
                    : url('/cliente/email/verify') );
  $soporte      = $soporte      ?? 'soporte@pactopia.com';
  $expiresHours = (int)($expiresHours ?? 24);
  $tz           = $tz ?? 'America/Mexico_City';
  $expiraEn     = Carbon::now($tz)->addHours($expiresHours)->format('Y-m-d H:i T');
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
