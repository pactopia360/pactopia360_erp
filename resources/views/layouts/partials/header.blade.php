<header class="header">
  {{-- IZQUIERDA: LOGO --}}
  <div class="header-left">
    <button class="menu-btn" id="sidebarToggleMobile" aria-label="Abrir menú" aria-expanded="false" aria-controls="sidebar">☰</button>

    <a href="{{ route('admin.home') }}" class="brand-link" aria-label="Ir a Home">
      <img class="brand-logo brand-dark"  src="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" alt="Pactopia 360">
      <img class="brand-logo brand-light" src="{{ asset('assets/admin/img/logo-pactopia360-dark.png')  }}" alt="Pactopia 360">
      <span class="brand-name">Pactopia360</span>
    </a>
  </div>

  {{-- CENTRO-IZQ: DATOS DEL USUARIO --}}
  <div class="header-usermeta" aria-live="polite">
    <strong>{{ auth('admin')->user()->nombre ?? 'Admin' }}</strong>
    <small>Panel Administrativo</small>
  </div>

  {{-- CENTRO: BUSCADOR GLOBAL con atajo (Ctrl/Cmd + K) --}}
  <form class="header-search" action="#" role="search" aria-label="Buscar">
    <div class="search-wrap">
      <input id="globalSearch" name="q" type="search" placeholder="Buscar en el panel…" autocomplete="off" />
      <span class="search-icon" aria-hidden="true">🔎</span>
      <span class="kbd" aria-hidden="true">Ctrl K</span>
    </div>
  </form>

  {{-- DERECHA: ACCIONES RÁPIDAS → TEMA → AVATAR → NOTIFICACIONES --}}
  <div class="header-right">

    {{-- ACCIONES RÁPIDAS --}}
    <details class="quick-menu">
      <summary class="notif-btn" aria-label="Acciones rápidas">⚡</summary>
      <div class="dropdown">
        <div class="dropdown-header">Acciones rápidas</div>
        <div class="dropdown-body">
          <a href="#" class="nav-link">➕ Nuevo cliente</a>
          <a href="#" class="nav-link">🧾 Generar CFDI</a>
          <a href="#" class="nav-link">💳 Registrar pago</a>
          <a href="#" class="nav-link">👤 Crear usuario admin</a>
          <a href="#" class="nav-link">⚙️ Configurar módulo</a>
        </div>
      </div>
    </details>

    {{-- TEMA --}}
    <button type="button" class="theme-btn" id="themeToggle" aria-label="Cambiar tema">
      <span class="icon">🌙</span>
      <span class="label">Modo oscuro</span>
    </button>

    {{-- PERFIL --}}
    <details class="avatar-menu">
      <summary aria-label="Perfil">
        <img class="avatar-img" src="{{ asset('assets/admin/img/avatar-default.png') }}" alt="Perfil">
      </summary>
      <div class="dropdown">
        <div class="dropdown-header">
          {{ auth('admin')->user()->nombre ?? 'Admin' }}
          <div class="email">{{ auth('admin')->user()->email ?? '' }}</div>
        </div>
        <div class="dropdown-body">
          <a href="#" class="nav-link">👤 Mi perfil</a>
          <a href="#" class="nav-link">🔒 Seguridad</a>
          <a href="#" class="nav-link">❓ Ayuda</a>
        </div>
        <div class="dropdown-foot">
          <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="logout-btn">Salir</button>
          </form>
        </div>
      </div>
    </details>

    {{-- NOTIFICACIONES / ALERTAS --}}
    <details class="notif-menu">
      <summary class="notif-btn" aria-label="Notificaciones" title="Notificaciones">
        🔔
        <span id="notifCount" class="badge" hidden>0</span>
      </summary>
      <div class="dropdown" style="min-width:340px;max-width:420px;padding:0">
        <div class="dropdown-header">Notificaciones & Alertas</div>
        <div class="dropdown-body" id="notifContainer">
          <div class="nav-link" style="pointer-events:none;opacity:.8">⚠️ <b>Alertas urgentes</b></div>
          <div id="alertList">
            <div class="empty">Sin alertas.</div>
          </div>
          <hr style="border:none;border-top:1px solid var(--header-border);margin:8px 0">
          <div class="nav-link" style="pointer-events:none;opacity:.8">📬 <b>Notificaciones</b></div>
          <div id="notifList">
            <div class="empty">Sin notificaciones.</div>
          </div>
        </div>
        <div class="dropdown-foot">
          <a href="#">Ver todas</a>
          <button type="button" id="notifMarkAll">Marcar leídas</button>
        </div>
      </div>
    </details>

  </div>
</header>
