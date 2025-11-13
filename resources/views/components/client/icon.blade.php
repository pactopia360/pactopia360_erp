{{-- resources/views/components/client/icon.blade.php --}}
{{-- Uso: <x-client.icon name="home" class="w-4 h-4" title="Inicio" /> --}}
@props([
  'name'       => null,             // (requerido) id del <symbol> en el sprite
  'class'      => 'ico',            // clases CSS extra
  'title'      => null,             // título accesible (si se omite, se marca aria-hidden)
  'desc'       => null,             // descripción accesible opcional
  'size'       => null,             // ej. "1em", "16px" o 20 (px)
  'sprite'     => null,             // ruta del sprite (<symbols>); default: assets/client/icons.svg
  'decorative' => false,            // true = aria-hidden, role="presentation"
])

@php
  if (!$name) { echo '<!-- x-client.icon: missing name -->'; return; }

  $sprite = $sprite ?: asset('assets/client/icons.svg');

  // IDs únicos para <title>/<desc>
  $rand = substr(bin2hex(random_bytes(3)), 0, 6);
  $baseId = 'ico-'.preg_replace('/[^a-z0-9\-]+/i', '-', $name).'-'.$rand;

  $titleId = $title ? ($baseId.'-title') : null;
  $descId  = $desc  ? ($baseId.'-desc')  : null;
  $labelledBy = trim(($titleId ? $titleId : '').' '.($descId ? $descId : ''));

  // Accesibilidad
  $isDecorative = $decorative || !$title;
  $role = $isDecorative ? 'presentation' : 'img';
  $ariaHidden = $isDecorative ? 'true' : 'false';

  // Tamaño (style inline para casos sin Tailwind)
  $style = null;
  if (!is_null($size)) {
    $s = is_numeric($size) ? ($size.'px') : (string)$size;
    $style = "width:{$s};height:{$s};";
  }
@endphp

<svg
  {{ $attributes->merge([
      'class'         => $class,
      'role'          => $role,
      'focusable'     => 'false',
      'aria-hidden'   => $ariaHidden,
      'aria-labelledby' => $isDecorative ? null : ($labelledBy ?: null),
    ]) }}
  @if($style) style="{{ $style }}" @endif
>
  @if($title)<title id="{{ $titleId }}">{{ $title }}</title>@endif
  @if($desc)<desc id="{{ $descId }}">{{ $desc }}</desc>@endif
  {{-- href y xlink:href para compatibilidad amplia --}}
  <use href="{{ $sprite }}#{{ $name }}" xlink:href="{{ $sprite }}#{{ $name }}" />
</svg>
