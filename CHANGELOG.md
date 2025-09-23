# Pactopia360 ERP · CHANGELOG

## [v1.1] – 2025-09-22
**Primera entrega estable (Local → Producción).**

### Added
- Login/logout administrativo y de cliente.
- Recuperación de contraseña (stub Mailgun).
- Dashboard Admin con KPIs básicos y gráficos (stub).
- Dashboard Cliente con saludo/perfil (stub).
- Planes base Free y Premium ($999 mensual/anual).
- Integración de Stripe (sandbox) para pagos cliente.
- Multi-DB: separación p360v1_admin y p360v1_clientes.
- Deploy funcional en Hostinger (MariaDB).
- Repo GitHub privado inicializado y conectado.

### Fixed
- 404 en `sidebar.csss` → corregido a `sidebar.css`.
- Variable indefinida `$rows` en vista Carritos → estandarizado a `$carritos`.
- Ambigüedad PSR-4 en modelo `Carrito` vs seeder → corregido nombres/namespace.
- APP_KEY en `.env.production` copiado del local.
- Guards/providers (`config/auth.php`) coherentes entre admin/cliente.

### Known Issues (post v1.1)
- Roles y permisos aún no implementados.
- Planes/licencias sin promociones ni cupones.
- Paywall por impago no implementado.
- Módulos ERP cliente (RH, Nómina, Checador, CFDI) aún pendientes.
- Portal Central (pactopia.com) no desarrollado.
