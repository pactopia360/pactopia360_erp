{{-- resources/views/emails/partials/preheader.blade.php --}}
{{-- Texto de vista previa en el listado del cliente de correo (oculto en el cuerpo) --}}
<span
  aria-hidden="true"
  role="presentation"
  dir="ltr"
  style="
    display:none !important;
    visibility:hidden !important;
    mso-hide:all;
    opacity:0 !important;
    color:transparent !important;
    height:0 !important;
    width:0 !important;
    overflow:hidden !important;
    max-height:0 !important;
    max-width:0 !important;
    font-size:1px !important;
    line-height:1px !important;
    mso-line-height-rule:exactly;
  "
>
  {{ $text ?? 'Pactopia360 · Accede de forma segura a tu cuenta.' }}
  {{-- Filler para evitar recortes del preheader en algunos clientes --}}
  &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
  &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
</span>
