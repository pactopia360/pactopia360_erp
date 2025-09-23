# Pactopia360 ERP · Guía de Despliegue (Local → Producción)

---

## 1) Requisitos
- PHP 8.3
- Composer
- MySQL 9.x (local) / MariaDB (prod)
- Node.js (opcional para Vite build)

---

## 2) Local (Windows/WAMP)

```powershell
cd C:\wamp64\www\pactopia360_erp
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB, MAIL, APP_URL en .env
php artisan migrate --database=mysql_admin --path=database/migrations/admin
php artisan migrate --database=mysql_clientes --path=database/migrations/clientes
php artisan db:seed --class=AdminSeeder --database=mysql_admin
php artisan serve
