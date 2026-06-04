# Roadmap — Ecommerce Multisitio tipo Magento (Laravel + React)

> Plan maestro del proyecto. Documento vivo: se actualiza al cerrar cada fase.
> Fuente original: `PROJECT_CONTEXT.md`. Progreso de ejecución: ver [`PROGRESO.md`](./PROGRESO.md).

## Objetivo

Plataforma ecommerce multisitio inspirada en Magento Open Source, más simple, modular y mantenible. Construcción **por fases**, incremental, sin intentar construir todo Magento de golpe.

## Stack

- Backend: **Laravel 13** · PHP 8.3
- Frontend: **React 19 + Inertia v3 + TypeScript + Tailwind v4**
- Auth: **Fortify** (admin) — base del starter kit
- Roles/permisos: **Spatie laravel-permission**
- DB: **MySQL**
- Colas: Laravel Queues (database) · Cache: Redis (opcional) · Storage: local → S3 a futuro
- Pagos futuros: Mercado Pago, Openpay, PayPal, Kueski
- Tests: Pest 4

## Decisiones arquitectónicas fijadas

1. **Multisitio = híbrido (dominio + prefijo de ruta).** Casos reales:
   - `interferenciales.com.mx` → store principal (por dominio).
   - `interferenciales.com.mx/sports` → store secundaria del mismo website (por **prefijo de ruta**).
   - `veterinaria.com.mx` → store de otro website (por **dominio propio**).
   - ⇒ `StoreResolver` resuelve **primero por Host**; si el host tiene stores por path, resuelve por el **primer segmento de ruta**; fallback a la store por defecto del website.
2. **Base de datos: MySQL** (el starter kit venía en SQLite).
3. **Roles y permisos: Spatie `laravel-permission`.** Permisos por-sitio vía tabla puente `admin_user_store_permissions`.
4. **Disciplina por fases:** no se avanza sin cerrar la fase actual (migraciones corren, modelos/relaciones ok, rutas responden, UI mínima, validaciones, permisos, tests básicos, no se rompe lo anterior).

## Punto de partida (auditado)

El repo es el **Laravel React Starter Kit**: Laravel 13, Inertia v3, React 19, TS, Tailwind v4, Fortify, Wayfinder, Pest 4. Solo existe el modelo `User` y 3 migraciones por defecto (`users`, `cache`, `jobs`). El auth scaffolding (login/register/settings) ya viene incluido ⇒ buena parte de Fase 1 y parte de Fase 2 ya están resueltas.

## Estructura objetivo (se crea en Fase 1, se llena por fase)

```
app/Domain/{Store,Catalog,Inventory,Customer,Cart,Checkout,Sales,Payment,Shipping,Promotion}
app/{Actions,Services}
app/Http/Controllers/{Admin,Storefront,Api}
resources/js/pages/{admin,storefront}/...   (se mantiene la raíz pages/ del starter kit)
```

---

## Hitos y fases

### 🟢 MVP 1 — Tienda funcional simple (vender de punta a punta)

| # | Fase | Notas / dependencias |
|---|------|----------------------|
| 1 | Base técnica | Casi cubierta por starter kit. Falta: estructura dominio, MySQL, Spatie, layouts admin/storefront, config infra, README. |
| 2 | Auth admin, usuarios, roles y permisos | Login/recuperación ya existen. Falta CRUD usuarios/roles/permisos + Spatie + menú dinámico + auditoría básica. |
| 3 | **Core multisitio** | Pieza central. `Website/Store/StoreDomain/StoreConfiguration` + `StoreResolver`/`StoreContext` híbrido + config por scope. Bloquea catálogo/precios/pagos. |
| 4 | Media manager | Polimórfico (`Media`/`Mediable`), storage local con interfaz para S3. |
| 5 | Productos simples | Depende de 3 (precio/asignación por store) y 4 (imágenes). |
| 6 | Categorías y atributos | Árbol por website + atributos reutilizables (base para configurables y filtros). |
| 7 | Inventario base | `available = physical - reserved`. `StockService` + reservas. Bloquea carrito/checkout. |
| 8 | Storefront básico | Home/header/footer/categorías/PDP por store. Consume 3,5,6,7. |
| 9 | Clientes ecommerce | Auth de cliente separado del admin, asociado a website. |
| 10 | Carrito | Invitado + registrado, merge, totales en backend. Depende de 7. |
| 11 | Métodos de envío | Por store; cálculo integrado a carrito/checkout. |
| 12 | Checkout | Crea orden `pending_payment` + reserva stock. Depende de 9,10,11. |
| 13 | Órdenes | Ciclo de vida + historial + snapshots. |
| 14 | Core de pagos | `PaymentGatewayInterface`, transacciones, webhooks, idempotencia. |
| 15 | Primera pasarela | **Mercado Pago** primero. Cierra el flujo de venta. |
| 25* | Emails transaccionales (subset) ✅ | Orden creada, pago aprobado/fallido, registro. Resto en MVP2. |

> **Mínimo de pago/envío en MVP1:** 1 método de envío (tarifa fija) + 1 pasarela (Mercado Pago).

### 🟡 MVP 2 — Operación ecommerce completa
Fase 7 robusto (movimientos/ajustes/historial) · 16 Invoices internas ✅ · 17 Shipments ✅ · 18 Productos configurables ✅ · mejoras de checkout ✅ · 21 (Openpay) ✅ · 26 Reportes básicos ✅ · 27 Logs/auditoría ✅ · 24 APIs básicas ✅.

### 🔵 MVP 3 — Magento avanzado
19 Bundles ✅ · 20 Descargables ✅ · 23 Reglas de catálogo ✅ · 22 Reglas de carrito y cupones ✅ · 21 (PayPal + Kueski) · 24 APIs completas · 28 QA/performance · 29 DevOps/producción.

---

## Detalle de fases (referencia)

> El detalle completo de cada fase (modelos, tablas, servicios, entregables) está en `PROJECT_CONTEXT.md`. Aquí solo el resumen ejecutable. Cada fase se planea en profundidad al iniciarla siguiendo el flujo:
> subtareas → migraciones → modelos → relaciones → services/actions → controllers → requests/validaciones → policies/middleware → UI React → tests → seeders → docs → comandos → checklist manual.

> Estado de ejecución detallado en [`PROGRESO.md`](./PROGRESO.md). Fases 1–18, 21, 22, 23, 24, 26, 27, 29 ✅ y Fase 25* ✅ terminadas.

### Fase 1 — Base técnica ✅
- MySQL en `.env`/`.env.example` + `config/database.php`.
- Estructura `app/Domain/*` + `app/Services`.
- Instalar Spatie `laravel-permission` + trait `HasRoles` en `User`.
- `AdminLayout` y `StorefrontLayout` + rutas placeholder `/admin` y `/`.
- Config infra (colas/mail/storage/logs) documentada en `.env.example`.
- README con comandos principales.
- **Verificación:** `migrate:fresh` contra MySQL · `composer run dev` levanta · `/`, `/admin`, `/login` cargan · `npm run build` + `types:check` · smoke test Pest · `pint --dirty`.

---

## Criterios generales de "terminado" (por fase)

- Migraciones corren correctamente.
- Modelos y relaciones funcionan.
- Rutas backend responden.
- UI mínima funciona.
- Validaciones principales implementadas.
- Permisos aplican donde corresponde.
- No se rompe una fase anterior.
- Hay instrucciones para probar manualmente.
- Hay resumen de archivos modificados + comandos necesarios.
