# Pactopia360 ERP · Módulos y Estado (v1.1)

Este documento lista los módulos principales del ERP y su estado actual al cierre de la versión **v1.1**.

---

## 1) Plataforma Administrativa (ERP Maestro)

| Módulo                | Estado | Notas |
|------------------------|--------|-------|
| Autenticación Admin    | ✅     | Login/logout + recuperación (stub Mailgun). |
| Roles/Permisos         | ❌     | Pendiente implementar con Spatie u otro. |
| Usuarios Internos      | 🟡     | CRUD parcial, falta roles/permisos. |
| Clientes (Empresas)    | 🟡     | Alta/edición básica, RFC único. |
| Planes/Licencias       | 🟡     | Free/Premium ($999 mensual/anual). Sin promos/cuotas aún. |
| Pagos/Facturación      | 🟡     | Stripe sandbox, sin CFDI/bloqueo. |
| Módulos ERP activados  | ❌     | Falta lógica de límites y activación/desactivación. |
| Timbres/HITS           | ❌     | No implementado aún. |
| Dashboard Admin        | 🟡     | KPIs stub + ApexCharts inicial. |
| Soporte/Tickets        | ❌     | No implementado. |
| Contabilidad Interna   | ❌     | No implementado. |
| Módulo Desarrollador   | ❌     | No implementado. |

---

## 2) Plataforma Cliente

| Módulo                 | Estado | Notas |
|-------------------------|--------|-------|
| Autenticación Cliente   | ✅     | Registro/login + wizard inicial. |
| Dashboard Cliente       | 🟡     | Vista stub con saludo/perfil. |
| Estado de Cuenta        | ❌     | No hay bloqueo/paywall. |
| Gestión de Empresas     | ❌     | Multi-RFC no implementado. |
| Módulos de Uso (RH, Nómina, Checador, CFDI, Reportes) | ❌ | Solo definidos en alcance. |
| Pagos/Suscripciones     | 🟡     | Stripe sandbox funcionando. |
| Soporte Cliente         | ❌     | Tickets/soporte no implementados. |

---

## 3) Portal Central (pactopia.com)

| Módulo                | Estado | Notas |
|------------------------|--------|-------|
| Registro/Login         | ❌     | No implementado. |
| Landing comercial      | ❌     | No implementado. |
| Pasarela inicial pago  | ❌     | No implementado. |
| Gobernanza/Distribución| ❌     | No implementado. |

---

## 4) Infraestructura

| Elemento               | Estado | Notas |
|-------------------------|--------|-------|
| Multi-DB (admin/clientes) | ✅   | Configurado y probado en local/prod. |
| GitHub Repo            | ✅     | https://github.com/pactopia360/pactopia360_erp |
| Deploy Producción      | ✅     | Hostinger con artisan migrate/cache/storage. |
| .env local/prod        | ✅     | Diferenciados y operativos. |
| NovaBot (QA/Session)   | 🟡     | Monitoreo de sesión, falta IA avanzada. |

---

## Leyenda
- ✅ Hecho  
- 🟡 Parcial / stub  
- ❌ Pendiente
