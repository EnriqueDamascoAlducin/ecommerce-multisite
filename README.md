# Ecommerce Multisitio (Laravel + React)

Plataforma ecommerce multisitio inspirada en Magento Open Source, construida por fases.

- **Roadmap completo:** [`docs/ROADMAP.md`](docs/ROADMAP.md)
- **Progreso / bitácora:** [`docs/PROGRESO.md`](docs/PROGRESO.md)

## Stack

Laravel 13 · PHP 8.3 · React 19 + Inertia v3 · TypeScript · Tailwind v4 · Fortify · Wayfinder · Spatie laravel-permission · MySQL · Pest 4.

## Requisitos

- PHP 8.3, Composer
- Node 18+ y npm
- MySQL/MariaDB

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_* en .env y crea la base de datos
php artisan migrate
npm install
npm run build   # o npm run dev en desarrollo
```

## Desarrollo

```bash
# Levanta server + queue + logs + vite a la vez
composer run dev

# O por separado:
php artisan serve --host=localhost   # backend en http://localhost:8000
npm run dev                          # frontend (Vite)
php artisan queue:listen             # colas (QUEUE_CONNECTION=database)
php artisan pail                     # logs en vivo
```

## Comandos útiles

```bash
php artisan migrate:fresh            # recrea el esquema
php artisan test --compact           # tests (Pest)
vendor/bin/pint --dirty              # formatea PHP modificado
npm run types:check                  # TypeScript
npm run lint                         # ESLint
```

## Estructura (dominio)

```
app/Domain/{Store,Catalog,Inventory,Customer,Cart,Checkout,Sales,Payment,Shipping,Promotion}
app/{Actions,Services}
app/Http/Controllers/{Admin,Storefront,Api}
resources/js/pages/{admin,storefront}/...
```

## Rutas base (Fase 1)

- `/` — storefront público (layout base).
- `/admin` — panel admin (protegido por auth).
- `/login`, `/register`, `/settings/*` — autenticación y ajustes (Fortify + starter kit).

## Entorno (WSL)

El proyecto vive en el filesystem de WSL. **Todo el toolchain debe ejecutarse dentro de WSL**
(no desde Windows: las rutas UNC `\\wsl.localhost\...` rompen Composer, Vite y PHPUnit).

- **PHP/Composer/Artisan/Pint:** ya disponibles en WSL (`php`, `composer`).
- **Node:** vía nvm. Cárgalo antes de usar npm:
  ```bash
  export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"
  npm install && npm run build   # o npm run dev
  ```
- **Tests:** el suite usa SQLite en memoria. Si el PHP de WSL no trae el driver, instala una vez:
  ```bash
  sudo apt install php8.3-sqlite3
  ```
  Alternativa sin SQLite: correr contra una BD MySQL de pruebas
  (`DB_CONNECTION=mysql DB_DATABASE=ecommerce-multisite-test php artisan test`).
- **DB:** MySQL/MariaDB en `127.0.0.1:3306`.

## Multisitio

Resolución **híbrida**: por dominio (`veterinaria.com.mx`) y por prefijo de ruta
(`interferenciales.com.mx/sports`). Ver detalle en [`docs/ROADMAP.md`](docs/ROADMAP.md).
