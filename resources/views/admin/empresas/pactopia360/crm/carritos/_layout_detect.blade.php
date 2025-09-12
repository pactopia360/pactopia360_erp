{{-- resources/views/admin/empresas/pactopia360/crm/carritos/_layout_detect.blade.php --}}
@php
  // Orden de preferencia de layouts a detectar
  $__layouts = [
    'admin.layouts.app',
    'layouts.admin',
    'layouts.app',
    'layouts.master',
    'layouts.main',
    'admin.layout',
  ];

  // Si ya viene seteado desde fuera, lo respetamos
  $__layout = $__layout ?? null;

  // Buscar el primer layout que exista
  foreach ($__layouts as $__c) {
    if (view()->exists($__c)) { $__layout = $__c; break; }
  }

  // Fallback final (permite inyectar $__default desde el include si se desea)
  $__layout = $__layout ?? ($__default ?? 'layouts.app');
@endphp
