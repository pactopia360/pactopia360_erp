{{-- resources/views/emails/cliente/verify_phone_text.blade.php --}}
Tu código de verificación Pactopia360: {{ $code ?? ($otp ?? '------') }}
Expira en {{ $ttl_minutes ?? 10 }} minutos.
@if(!empty($phone))
Teléfono: {{ $phone }}
@endif

Si no solicitaste este código, ignora este correo.
Soporte: soporte@pactopia.com
