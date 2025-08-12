<!DOCTYPE html>
<html lang="es">
<head>
  @include('layouts.partials.head')
</head>
<body>
  <div class="app">
    {{-- Sidebar --}}
    @include('layouts.partials.sidebar')

    {{-- Contenido principal --}}
    <div class="main">
      @include('layouts.partials.header')

      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>

  {{-- NovaBot flotante --}}
  @include('layouts.partials.novabot')

  {{-- Scripts comunes + hooks por página --}}
  @include('layouts.partials.scripts')
  @stack('scripts')
</body>
</html>
