# HANDOFF — Cómo retomar este proyecto (para cualquier agente/IA)

> Léeme **antes de tocar código**. Resume el estado, las reglas y los gotchas del
> entorno para que cualquier agente (Claude Code, OpenCode, etc.) se ponga al día
> sin romper la consistencia. Fuente de verdad del avance: [`PROGRESO.md`](./PROGRESO.md).
> Plan maestro: [`ROADMAP.md`](./ROADMAP.md).

## Qué es esto

Plataforma **ecommerce multisitio** tipo Magento (más simple y mantenible).
Stack: **Laravel 13 · PHP 8.3 · React 19 + Inertia v3 + TypeScript · Tailwind v4 ·
Pest 4 · Spatie laravel-permission · MySQL/MariaDB**. Auth admin con Fortify;
auth de cliente en guard separado (`customer`).

## Estado actual

- **MVP 1 completo: Fases 1→15 cerradas.** Se vende de punta a punta con pago real.
- **227 tests** verdes (4 skipped).
- **Siguiente:** subset de **Fase 25 — emails transaccionales** (orden creada, pago
  aprobado/fallido, registro). Después arranca MVP 2 (fases 16+).
- El detalle de cada fase cerrada está en `PROGRESO.md` (bitácora fechada).

## Reglas de trabajo (NO negociables)

1. **Disciplina por fases:** no se avanza a la siguiente sin cerrar la actual.
   Flujo de cada fase: migraciones → modelos → relaciones → services/actions →
   controllers → requests/validaciones → policies/middleware → UI React → tests →
   seeders → docs → verificación.
2. **Toda fase cierra con verificación en verde** (ver checklist abajo) y con
   **actualización de `PROGRESO.md`** (estado + entrada de bitácora fechada) y de
   la línea de estado en `ROADMAP.md`.
3. **Cada cambio se prueba programáticamente** (test nuevo o actualizado), no con
   scripts de verificación sueltos ni tinker.
4. **Sigue las convenciones existentes** (revisa archivos hermanos antes de crear).
   Las guías del repo están en `CLAUDE.md` (Laravel Boost).
5. **Un solo agente a la vez** sobre los mismos archivos. Trabajo secuencial:
   parar → commit → el siguiente continúa leyendo el commit.

## Checklist de cierre de fase (todo debe pasar)

```bash
# Dentro de WSL, en la raíz del proyecto:
vendor/bin/pint --dirty --format agent     # formato PHP
DB_CONNECTION=mysql DB_DATABASE=ecommerce-multisite-test php artisan test --compact
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"
npm run types:check                        # tsc --noEmit
npm run build                              # vite + genera rutas/acciones de Wayfinder
```

## Gotchas del entorno (IMPORTANTES)

- **El proyecto vive en el FS de WSL.** Ejecuta SIEMPRE el toolchain **dentro de
  WSL**. Desde Windows, las rutas UNC (`\\wsl.localhost\...`) rompen Composer,
  Vite (rolldown win32) y PHPUnit.
- **Node** vía nvm en WSL: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"`
  antes de cualquier `npm`.
- **Tests contra MySQL de pruebas:** el PHP de WSL no trae `pdo_sqlite`. Usa la BD
  `ecommerce-multisite-test` (MariaDB/WAMP, root/`mysql`, 127.0.0.1:3306) con
  `DB_CONNECTION=mysql DB_DATABASE=ecommerce-multisite-test`. BD de desarrollo:
  `ecommerce-multisite`.
- **Wayfinder:** `resources/js/{routes,actions,wayfinder}` están en `.gitignore`
  (se generan). Tras clonar o cambiar de máquina, corre `npm run build` (o
  `php artisan wayfinder:generate`) antes de que el frontend compile.
- **Migraciones con el mismo timestamp** se ordenan alfabéticamente: renómbralas
  si hay dependencias de FK.
- **Ruta catch-all de tienda** (`{store_code}` en `routes/web.php`): su regex
  excluye segmentos reservados (`admin`, `cuenta`, `carrito`, `checkout`,
  `webhooks`, etc.). Si agregas una ruta top-level nueva, **añádela a esa regex**.

## Arquitectura clave (para no reinventar)

- **Multisitio híbrido:** `StoreResolver` resuelve por dominio → prefijo de ruta →
  fallback. `StoreContext` (singleton) guarda el sitio de la petición.
- **Permisos por scope:** Spatie + tabla puente `admin_user_store_permissions`.
- **Inventario:** `available = physical − reserved`. Reservas por referencia
  (`order:{id}`); el checkout reserva y la cancelación/reembolso libera.
- **Pagos (Fase 14/15):** `app/Domain/Payment` — `PaymentGateway` (contrato),
  `PaymentGatewayRegistry` (singleton en `AppServiceProvider`, fuente de los
  métodos de pago), `PaymentService` (idempotente vía `payment_webhook_events`).
  Pasarelas: `OfflineGateway` y `MercadoPagoGateway`. Webhook:
  `POST /webhooks/payments/{gateway}` (sin CSRF, fuera de `resolve.store`).

## Seguridad / pendientes conocidos

- `superadmin@example.com` / `password` (seeder) **debe cambiarse antes de prod.**
- Mercado Pago: en producción configurar `notification_url` accesible y
  `MERCADOPAGO_WEBHOOK_SECRET` (habilita verificación de firma `x-signature`).
- El stock se libera en cancelación/reembolso pero **no se consume** hasta el
  envío (shipments quedan para MVP 2).

## Comandos útiles

```bash
php artisan migrate --no-interaction        # aplicar migraciones (BD dev)
php artisan db:seed                          # datos de demo
php artisan route:list --except-vendor       # inspeccionar rutas
composer run dev                             # server + vite + queue + logs
```
