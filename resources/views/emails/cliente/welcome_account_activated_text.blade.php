{{-- resources/views/emails/cliente/welcome_account_activated_text.blade.php --}}

@php
  $producto     = $producto     ?? 'Pactopia360';
  $nombre       = $nombre       ?? 'Usuario';
  $email        = $email        ?? null;
  $rfc          = $rfc          ?? null;
  $tempPassword = $tempPassword ?? null;
  $is_pro       = isset($is_pro) ? (bool)$is_pro : null;

  $loginUrl     = $loginUrl ?? ( \Illuminate\Support\Facades\Route::has('cliente.login')
                    ? route('cliente.login')
                    : url('/cliente/login') );

  $soporte      = $soporte ?? 'soporte@pactopia.com';
@endphp

{{-- Preheader visible en texto plano --}}
Tu cuenta ya está activa. Inicia sesión y cambia tu contraseña.

Hola {{ $nombre }},

🎉 ¡Tu cuenta en {{ $producto }} ya está activa!

Datos de acceso:
@isset($email)
- Correo: {{ $email }}
@endisset
@isset($rfc)
- RFC: {{ $rfc }}
@endisset
@isset($tempPassword)
- Contraseña temporal: {{ $tempPassword }}
@endisset

Inicia sesión aquí:
{{ $loginUrl }}

@if(!is_null($is_pro))
  @if($is_pro)
Tu plan PRO incluye soporte prioritario, mayor almacenamiento y más timbres al mes.
  @else
Tu cuenta FREE está lista para usarse. Puedes actualizar a PRO cuando quieras.
  @endif
@else
Tu cuenta está lista para usarse. Si necesitas más capacidad, considera actualizar a PRO.
@endif

Por seguridad, cambia tu contraseña en el primer acceso.

— Equipo {{ $producto }}
Soporte: {{ $soporte }}
