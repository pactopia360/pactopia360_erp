@php
  $row     = $row ?? ($carrito ?? null);
  $estados = $estados ?? ['abierto','convertido','cancelado','nuevo'];
@endphp

@include('admin.empresas.pactopia360.crm.carritos._form', [
  'row'     => $row,
  'estados' => $estados,
])
