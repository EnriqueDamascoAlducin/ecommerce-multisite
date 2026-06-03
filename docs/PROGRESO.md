# Progreso del proyecto

> Registro vivo de avance. Roadmap completo en [`ROADMAP.md`](./ROADMAP.md).
> Última actualización: 2026-06-03 (Fase 18 cerrada).

## Estado global

| Fase | Estado |
|------|--------|
| 1 — Base técnica | 🟢 Terminada |
| 2 — Auth admin, usuarios, roles y permisos | 🟢 Terminada |
| 3 — Core multisitio | 🟢 Terminada |
| 4 — Media manager | 🟢 Terminada |
| 5 — Catálogo: productos simples | 🟢 Terminada |
| 6 — Categorías y atributos | 🟢 Terminada |
| 7 — Inventario base | 🟢 Terminada |
| 8 — Storefront básico | 🟢 Terminada |
| 9 — Clientes ecommerce | 🟢 Terminada |
| 10 — Carrito | 🟢 Terminada |
| 11 — Métodos de envío | 🟢 Terminada |
| 12 — Checkout | 🟢 Terminada |
| 13 — Órdenes | 🟢 Terminada |
| 14 — Core de pagos | 🟢 Terminada |
| 15 — Primera pasarela (Mercado Pago) | 🟢 Terminada |
| 25* — Emails transaccionales (subset MVP1) | 🟢 Terminada |
| 16 — Invoices/facturas internas | 🟢 Terminada |
| 17 — Shipments/envíos | 🟢 Terminada |
| 18 — Productos configurables | 🟢 Terminada |
| 19–29 | ⬜ Pendiente |

Leyenda: ⬜ pendiente · 🟡 en curso · 🟢 terminada · 🔴 bloqueada

---

## Bitácora

### 2026-06-03 — Fases 14 y 15 cerradas (Core de pagos + Mercado Pago)

**Fase 14 — Core de pagos (agnóstico de pasarela):**
- **Migraciones/modelos:** `payment_transactions` (orden, gateway, tipo, estado, monto, ids externos, `idempotency_key` único, payload, `processed_at`) y `payment_webhook_events` (idempotencia de notificaciones vía `unique(gateway, event_id)`). Relación `Order::transactions()`.
- **Contrato + DTOs (`app/Domain/Payment`):** interfaz `Contracts\PaymentGateway` (`code`/`label`/`isAvailable`/`start`/`parseWebhook`), enum `PaymentStatus` (con mapeo a estado de orden y a liberación de stock), value objects `PaymentResult` y `WebhookResult`, `PaymentException`.
- **`PaymentGatewayRegistry`:** fuente única de los métodos de pago del checkout; sólo expone las pasarelas **configuradas** (`isAvailable`). Registrado como singleton en `AppServiceProvider`.
- **`PaymentService`:** `start()` (registra transacción + redirige cuando aplica), `handleWebhook()` **idempotente** (dedupe por evento, transacción atómica) y `applyToOrder()` (transiciona la orden sin regresar desde estados ya liquidados; libera stock en cancelación/reembolso).
- **`OfflineGateway`:** transferencia/pago pendiente; deja la orden `pending_payment` para confirmación manual. Siempre disponible.
- **Config:** `config/payments.php` + claves `MERCADOPAGO_*`/`PAYMENTS_DEFAULT` en `.env.example`.

**Fase 15 — Mercado Pago (Checkout Pro):**
- **`MercadoPagoGateway`:** crea **preferencia** (HTTP a `/checkout/preferences`) con items, `external_reference`, `back_urls` (éxito/pendiente/fallo) y `notification_url`; redirige al `init_point`. El webhook consulta `/v1/payments/{id}` y mapea estados (`approved→Paid`, `pending/in_process→Pending`, `rejected→Failed`, `cancelled→Cancelled`, `refunded/charged_back→Refunded`). Verificación opcional de firma `x-signature` (HMAC) cuando hay `webhook_secret`. `eventId = "{payId}:{status}"` para procesar cada transición una sola vez.
- **Integración checkout:** `CheckoutController::store` inicia el pago tras crear la orden; redirige al checkout alojado (`Inertia::location`) o a éxito (offline). Nuevas páginas/return URLs: `checkout.failure` + `checkout-failure.tsx`.
- **Webhook:** `POST /webhooks/payments/{gateway}` (`PaymentWebhookController`), fuera de `resolve.store`, exento de CSRF (`webhooks/*`), `webhooks` añadido a la regex de segmentos reservados. Responde 404 sólo si la pasarela no existe; siempre 200 al ack para evitar reintentos.
- **Admin:** el detalle de orden muestra ahora la tabla de **Pagos** (transacciones).

**Verificación (todo verde):**
- `pint --dirty` ✓ · `tsc --noEmit` ✓ · `npm run build` ✓ · suite completa **227 passed, 4 skipped** (608 assertions). 13 tests nuevos (`PaymentCoreTest` + `MercadoPagoTest`, este último con `Http::fake`).

**Notas / decisiones:**
- La lista de métodos de pago del checkout viene del **registry** (no de una constante); MP sólo aparece si hay `MERCADOPAGO_ACCESS_TOKEN`.
- El stock reservado en el checkout permanece reservado al pagar; se libera en cancelación o reembolso (no se consume hasta el envío, pendiente en MVP2).
- Pago aprobado deja la orden en `paid`; la confirmación de pago **offline** se hace manualmente desde el admin (Fase 13).

### 2026-06-02 — Arranque y planificación

**Hecho:**
- Auditado el repo: es el **Laravel React Starter Kit** (Laravel 13, Inertia v3, React 19, TS, Tailwind v4, Fortify, Wayfinder, Pest 4). Solo modelo `User` y migraciones por defecto.
- Fijadas las decisiones arquitectónicas (ver ROADMAP): multisitio **híbrido** dominio+prefijo, **MySQL**, **Spatie laravel-permission**.
- Reorganizadas las 29 fases del `PROJECT_CONTEXT.md` en 3 MVPs con dependencias.
- Creados `docs/ROADMAP.md` y `docs/PROGRESO.md`.

**Entorno detectado:**
- PHP 8.3 disponible en Windows (WAMP, `php8.3.28`) y en WSL (8.3.31).
- **MySQL 8.4.7 vía WAMP** corriendo en Windows (puerto 3306 abierto). Binario: `C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe`.
- MySQL **no** está instalado dentro de WSL.

### 2026-06-03 — Fase 1 cerrada (Base técnica)

**Hecho:**
- **MySQL/MariaDB:** `.env` (root/`mysql`, BD `ecommerce-multisite`) y `.env.example` actualizados; BD creada; `migrate` y `migrate:fresh` corren limpio.
- **Estructura de dominio:** `app/Domain/{Store,Catalog,Inventory,Customer,Cart,Checkout,Sales,Payment,Shipping,Promotion}`, `app/Services` y `app/Http/Controllers/{Admin,Storefront,Api}` (con `.gitkeep`).
- **Spatie laravel-permission 8.0** instalado, migración publicada y migrada; trait `HasRoles` en `app/Models/User.php`.
- **Layouts + rutas:** `StorefrontLayout` y `AdminLayout` (`resources/js/layouts/`), páginas placeholder `pages/storefront/home.tsx` y `pages/admin/dashboard.tsx`, switch de layout en `app.tsx`, rutas `/` (home) y `/admin` (admin.dashboard, auth+verified) en `routes/web.php`.
- **README.md** con comandos y notas de entorno WSL.

**Verificación (todo verde):**
- `migrate:fresh` ✓ (incluye tablas de Spatie) · `pint --dirty` ✓ · `tsc --noEmit` ✓ · `npm run build` ✓ · smoke test `tests/Feature/SmokeTest.php` ✓ (3 passed).

**⚠️ Notas de entorno (importantes para todas las fases):**
- El proyecto está en el FS de WSL ⇒ **ejecutar el toolchain dentro de WSL**. Desde Windows, las rutas UNC (`\\wsl.localhost\...`) rompen Composer, Vite (rolldown win32) y PHPUnit.
- **Node** vía nvm en WSL: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` antes de `npm`.
- **Tests:** el PHP de WSL no trae `pdo_sqlite`. El suite usa sqlite en memoria, así que la verificación se corrió contra una BD MySQL de pruebas (`ecommerce-multisite-test`). Para usar el default rápido: `sudo apt install php8.3-sqlite3` (requiere contraseña del usuario).

**Siguiente:** Fase 2 — Auth admin, usuarios, roles y permisos.

---

### 2026-06-03 — Fase 2 cerrada (Auth admin, usuarios, roles y permisos)

**Hecho:**
- **Roles y permisos (Spatie):** `RolesAndPermissionsSeeder` con 20 permisos y 8 roles (Super Admin, Administrador, Catálogo, Inventario, Ventas, Marketing, Soporte, Solo lectura). Usuario **`superadmin@example.com` / `password`** (cambiar en prod). Middleware `role`/`permission`/`role_or_permission` registrados en `bootstrap/app.php`.
- **CRUD Usuarios:** `Admin/UserController` + `StoreUserRequest`/`UpdateUserRequest` (asignación de roles, no permite auto-eliminarse).
- **CRUD Roles + Permisos:** `Admin/RoleController` (rol `Super Admin` protegido) + `Admin/PermissionController` (listado solo lectura) + requests.
- **Rutas:** `routes/admin.php` con prefijo `admin.` y middleware de permiso por acción.
- **Auditoría:** modelo `AuditLog` + migración `audit_logs` (polimórfico) + servicio `App\Services\AuditLogger`, invocado en cada create/update/delete.
- **Frontend:** permisos/roles compartidos vía `HandleInertiaRequests`; hook `usePermissions`; menú dinámico en `AdminLayout` + toasts de flash (sonner); páginas `admin/users/*`, `admin/roles/*`, `admin/permissions/index` con formularios Wayfinder.
- **Tests:** `tests/Feature/Admin/UserManagementTest.php` y `RoleManagementTest.php` (CRUD + enforcement de permisos + protección de Super Admin).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **50 passed, 4 skipped** (contra BD MySQL de pruebas).

**Siguiente:** Fase 3 — Core multisitio.

---

### 2026-06-03 — Fase 3 cerrada (Core multisitio)

**Hecho:**
- **Modelos + migraciones:** `Website`, `Store`, `StoreDomain`, `StoreView`, `StoreConfiguration`, `AdminUserStorePermission` (+ relaciones y factories). FK ordenadas.
- **Servicios (`app/Domain/Store`):** `StoreResolver` (resolución **híbrida**: dominio → prefijo de ruta → fallback a website por defecto), `StoreContext` (singleton de petición), `ScopedConfigService` (herencia store→website→global), `StorePermissionService` (acceso por sitio; Super Admin = todo), `AdminScopeManager` (scope en sesión).
- **Middleware `ResolveStore`** aplicado al storefront; sitio resuelto compartido a Inertia (`store`); scope admin compartido (`adminScope`).
- **Seeder `MultisiteSeeder`** con los sitios reales: Interferenciales (dominio + tienda `sports` por prefijo) y Veterinaria (dominio propio) + `localhost`/`127.0.0.1` para dev.
- **Admin:** CRUD `Websites` y `Stores` (con gestión de dominios e is_default único por website), pantalla de **Configuración por scope** y **selector de scope** en el `AdminLayout`. Rutas bajo `permission:settings.stores`.
- **Tests:** `Store/StoreResolverTest`, `Store/ScopedConfigTest`, `Store/StorePermissionServiceTest`, `Admin/MultisiteManagementTest` (23 tests nuevos).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **73 passed, 4 skipped**.

**Cómo probar la resolución (dev):** `composer run dev` y visita `http://localhost:8000/` (resuelve a Interferenciales). El selector de scope del admin permite editar configuración global / por website / por tienda.

**Siguiente:** Fase 4 — Media manager.

---

### 2026-06-03 — Fase 4 cerrada (Media manager)

**Hecho:**
- **Esquema:** tablas `media` (disk, directory, filename, mime, size, is_image, visibility public/private, title/alt, uploaded_by) y `mediables` (pivot polimórfico con collection, is_primary, sort_order). Modelos `Media` y `Mediable` (MorphPivot).
- **Trait `HasMedia`** (`app/Models/Concerns`): `media()`, `mediaInCollection()`, `primaryMedia()`, `attachMedia()`, `syncMediaCollection()` (colecciones, imagen principal, orden). Aplicado a `Website` y `Store`.
- **`MediaService`:** subida validada al disco `public` (públicos) o `local` (privados), borrado seguro (archivo + registro, cascada de vínculos). Listo para S3 cambiando el disco.
- **Descarga privada** vía URL firmada (`media.download` + middleware `signed`) — base para productos descargables.
- **Permisos** `media.view/upload/delete` en el seeder, asignados a Super Admin/Administrador/Catálogo/Marketing/Solo lectura.
- **Admin:** biblioteca de medios (`/admin/media`) con subida múltiple, grid con thumbnails, edición de título/alt (modal) y borrado. Ítem "Medios" en el menú.
- **Tests:** `Media/MediaServiceTest`, `Media/HasMediaTest`, `Admin/MediaManagementTest` (14 tests: subida pública/privada, borrado, trait/colecciones, descarga firmada y rechazo sin firma, permisos).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **87 passed, 4 skipped**. (`php artisan storage:link` ejecutado para servir medios públicos en dev.)

**Siguiente:** Fase 5 — Catálogo: productos simples.

---

### 2026-06-03 — Fase 5 cerrada (Catálogo: productos simples)

**Hecho:**
- **Esquema + modelos:** `products` (type, sku único, slug único, descripciones, status, visibility, weight, attributes JSON), `product_prices` (precio base con `store_id` null + overrides por tienda, precio especial con fechas), `product_stores` (activación/visibilidad por tienda). `Product` usa el trait `HasMedia` (galería vía Fase 4). Factories incluidas.
- **`ProductPricingService`:** precio efectivo por tienda (override de tienda → base) con precio especial vigente por fechas.
- **Admin `ProductController`:** index con **búsqueda** (nombre/SKU) y **filtro por estado**, CRUD con slug auto-único, precio base + especial, **precio y activación por tienda**, y **galería** (selección desde la biblioteca de medios; primera = principal). Rutas bajo `permission:catalog.products.*`.
- **UI:** `admin/products` index (tabla con miniatura, búsqueda/filtros) y formulario (datos, precio base/especial, tabla por tienda, selector de imágenes). Ítem "Productos" en el menú.
- **Tests:** `Catalog/ProductPricingTest` (4) y `Admin/ProductManagementTest` (9): CRUD, slug auto, SKU único, precio/activación por tienda, búsqueda, enforcement.

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **100 passed, 4 skipped**.

**Siguiente:** Fase 6 — Categorías y atributos (árbol por website, atributos reutilizables y valores por producto; base para filtros y configurables).

---

### 2026-06-03 — Fase 6 cerrada (Categorías y atributos)

**Hecho:**
- **Esquema + modelos:** `categories` (árbol por website con `parent_id` self-FK, slug único por website, descripción, SEO meta, orden, activa) + pivote `category_product`; `attributes` (code único, type, flags `is_required/filterable/visible/configurable`), `attribute_options` (label/value por atributo), `product_attribute_values` (valor por producto+atributo, multiselect en JSON). Modelos `Category`, `Attribute`, `AttributeOption`, `ProductAttributeValue` con relaciones; `Product` gana `categories()` y `attributeValues()`.
- **`CategoryService`:** construcción de **árbol anidado** por website y **lista plana** con indentación para el `<select>` de categoría padre (poda la propia rama para evitar ciclos).
- **Admin:** CRUD de **categorías** (selector de website + árbol visual, padre, SEO, slug auto-único por website) y CRUD de **atributos** (editor dinámico de opciones para select/multiselect). Rutas bajo `permission:catalog.categories.*` y `catalog.attributes.*` (permisos nuevos en el seeder; el rol Catálogo los hereda).
- **Integración con producto:** el formulario de producto ahora asigna **categorías** (checkboxes por website) y captura **valores de atributo** (input según tipo: text/textarea/number/select/multiselect/boolean/date).
- **Seeder `CatalogSeeder`:** categorías de ejemplo (Interferenciales y Veterinaria) + atributos color/talla (select), material (text), garantía (number).
- **Tests:** `Catalog/CategoryManagementTest` (8: árbol, slug por website, padre del mismo website, no-auto-padre, permisos), `Catalog/AttributeManagementTest` (9: tipos, opciones, code único/formato, limpieza de opciones), `Catalog/ProductCatalogAssignmentTest` (4: categorías, valores simples, multiselect JSON, limpieza).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓.

**Siguiente:** Fase 7 — Inventario base.

---

### 2026-06-03 — Fase 7 cerrada (Inventario base y stock robusto)

**Hecho:**
- **Esquema + modelos:** `inventory_sources` (almacenes: code único, default/activo), `inventory_stocks` (físico/reservado por producto+fuente, `manage_stock`, `allow_backorders`, umbral de stock bajo; **`available_qty` = físico − reservado** como accessor), `stock_movements` (historial auditable: tipo in/out/adjustment/reservation/release, delta con signo, `balance_after`, motivo, referencia, usuario), `stock_reservations` (cantidad, referencia, estado active/released/consumed, expiración). `Product` gana `inventoryStocks()` y `totalAvailableQty()`.
- **Servicios (`app/Domain/Inventory`):** `StockService` (punto único de mutación del físico con `adjust`/`setPhysical`, registra cada movimiento; `lockForUpdate`), `StockAvailabilityChecker` (`isAvailable`/`isAvailableBySku` respetando `manage_stock` y backorders), `StockReservationService` (`reserve`/`release`/`consume`/`releaseByReference`; lanza `InsufficientStockException` si no hay disponible).
- **Admin:** sección **Inventario** (lista de productos con físico/reservado/disponible, badge de **stock bajo**, búsqueda) con pantalla por producto para **ajustar stock por almacén** + flags + **historial de movimientos**; CRUD de **Almacenes** (default único, no se borra el default). Rutas bajo `permission:inventory.view` (lectura) e `inventory.adjust` (escritura).
- **Seeder `InventorySeeder`:** almacén `default` "Almacén principal".
- **Tests:** `Inventory/StockServiceTest` (7: fórmula disponible, ajuste/movimiento, setPhysical, manage_stock/backorders, por SKU, stock bajo), `Inventory/StockReservationTest` (6: reservar, insuficiente lanza, liberar, consumir, por referencia, backorders), `Inventory/InventoryManagementTest` (8: index, ajuste, CRUD almacenes, default protegido, enforcement).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **142 passed, 4 skipped** (310 assertions).

**Siguiente:** Fase 8 — Storefront básico (home/header/footer/categorías/PDP por store; consume multisitio + catálogo + inventario).

---

### 2026-06-03 — Fase 8 cerrada (Storefront básico)

**Hecho:**
- **Resolución de prefijo:** `StoreContext` ahora expone `pathPrefix` (poblado por `StoreResolver` cuando la tienda se resuelve por path, p. ej. `/sports`). Se comparte a Inertia junto con el **menú de categorías raíz** del website (`store.menu`).
- **`StorefrontController`:** `home` (destacados activos en la tienda), `category` (listado por categoría del website, paginado) y `product` (PDP: precio por tienda, galería, **en stock/agotado** vía `StockAvailabilityChecker`, atributos visibles, categorías). Toda query filtra por **producto activo + visible + habilitado en la tienda actual**. Caso sin tienda resuelta degradado con elegancia (home vacía).
- **Rutas:** catálogo en la raíz (`/`, `/c/{slug}`, `/p/{slug}`) y **bajo prefijo de tienda** (`/{store_code}/...`) para multisitio por path; el grupo con prefijo va al final y excluye por regex los segmentos reservados (`admin`, `cuenta`, `login`, …) para no capturarlos.
- **UI:** `storefront-layout` con header (logo + menú de categorías + enlaces de cuenta) y footer; helper `useStoreUrls`/`formatPrice` (`lib/storefront.ts`) que respeta el prefijo de tienda; páginas `home`, `category`, `product` (galería con miniaturas) y `ProductCard` reutilizable.
- **Tests:** `Storefront/StorefrontCatalogTest` (8: home por tienda, exclusión de no habilitados, listado por categoría, 404 categoría/producto, precio por tienda, hidden/inactivo inaccesibles).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓.

**Siguiente:** Fase 9 — Clientes ecommerce.

---

### 2026-06-03 — Fase 9 cerrada (Clientes ecommerce)

**Hecho:**
- **Esquema + modelos:** `customers` (asociado a website, **email único por website**) y `customer_addresses` (envío/facturación con defaults), + tabla `customer_password_reset_tokens`. `Customer` es `Authenticatable` con notificación de reseteo propia (`CustomerResetPasswordNotification`, enlaza a la ruta del storefront).
- **Auth separado del admin:** guard **`customer`** + provider `customers` + broker de contraseñas `customers` en `config/auth.php`. El share de Inertia usa explícitamente el guard `web` para el admin y expone `customer` aparte. `redirectGuestsTo` envía los invitados de `/cuenta/*` al login del cliente y el resto al de Fortify.
- **Flujos:** registro, login, logout, recuperación y restablecimiento de contraseña (todo **acotado al website actual**); perfil (datos + cambio de contraseña) y **CRUD de direcciones** (default único de envío/facturación, sin acceso a direcciones de otro cliente). URLs bajo `/cuenta` para no chocar con Fortify.
- **UI:** páginas `storefront/auth/*` (login, registro, recuperar, restablecer) y `storefront/account/*` (perfil, direcciones con alta/edición/baja) + navegación de cuenta; el header muestra la sesión del cliente.
- **Seeder `CustomerSeeder`:** cliente demo `cliente@example.com` / `password` con dirección.
- **Tests:** `Storefront/CustomerAuthTest` (9) y `Storefront/CustomerAddressTest` (6): registro/login/logout por website, email único por website y reusable entre websites, aislamiento de direcciones, default único, redirección de invitados.

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **165 passed, 4 skipped** (410 assertions).

**Siguiente:** Fase 10 — Carrito (invitado + registrado, merge, totales en backend; depende de inventario).

---

### 2026-06-03 — Fase 10 cerrada (Carrito)

**Hecho:**
- **Esquema + modelos:** `carts` (por tienda; dueño = `customer_id` o `session_token` de invitado; status, currency, `expires_at`), `cart_items` (snapshot de sku/nombre, cantidad, `unit_price`; único por `cart_id+product_id`) y `cart_item_options` (preparado para configurables/cupones futuros). `CartItem` calcula `line_total`.
- **Servicios (`app/Domain/Cart`):** `CartService` (resuelve el carrito de la tienda+dueño actual, agrega/actualiza/elimina validando **stock** y **precio vigente**; refresca el precio unitario al precio por tienda en cada carga), `CartTotalsCalculator` (subtotal/total en backend, descuentos en 0 para cupones futuros) y `CartMerger` (fusiona el carrito de invitado en el del cliente sumando cantidades). Nuevo `StockAvailabilityChecker::canFulfill` (disponibilidad total por todas las fuentes, respeta `manage_stock`/backorders).
- **Carrito de invitado** identificado por token en sesión; **merge automático** al iniciar sesión o registrarse.
- **`CartController`** (`/carrito`): index, store, update (cantidad; 0 = eliminar), destroy; valida pertenencia del ítem al carrito del visitante. Errores de stock/disponibilidad se muestran como flash.
- **UI:** página de carrito (cantidades, eliminar, resumen con total de backend), botón **Agregar al carrito** en la PDP y **badge** con el conteo en el header (resumen compartido por Inertia). Botón de checkout deshabilitado (llega en Fase 12).
- **Tests:** `Cart/CartTest` (10) y `Cart/CartMergeTest` (2): agregar/acumular, validar stock, totales, actualizar, quitar, refresco de precio, oculto no agregable, aislamiento entre carritos y fusión invitado→cliente. Helper `sellableProduct` en `tests/Pest.php`.

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **177 passed, 4 skipped** (456 assertions).

**Siguiente:** Fase 11 — Métodos de envío (por tienda; cálculo integrado al carrito/checkout).

---

### 2026-06-03 — Fase 11 cerrada (Métodos de envío)

**Hecho:**
- **Esquema + modelos:** `shipping_methods` (catálogo global: code, tipo `flat_rate`/`free_shipping`/`pickup`, activo), `store_shipping_methods` (habilitación y config **por tienda**: label, `free_over`, `min/max_subtotal`, `countries` JSON) y `shipping_rates` (tarifas por tramo de subtotal). `carts.shipping_method_code` para el método elegido.
- **Servicios (`app/Domain/Shipping`):** `ShippingRateCalculator` (costo según tipo, `free_over` y tramos), `ShippingMethodResolver` (disponibilidad por subtotal y país) y `ShippingService` (fachada: opciones para un carrito, costo del método elegido, validación de disponibilidad).
- **Integración con el carrito:** `CartTotalsCalculator` ahora incluye **envío** (subtotal − descuento + envío). `CartService::setShippingMethod` valida disponibilidad y **purga** el método si deja de aplicar (cambió el subtotal). País de destino tomado de la dirección de envío por defecto del cliente.
- **Storefront:** endpoint `POST /carrito/envio`; la página del carrito muestra **selector de método** con su costo y el total recalculado en backend.
- **Admin:** CRUD de métodos globales (`/admin/shipping`) y **configuración por tienda** (`/admin/shipping-stores`: habilitar, etiqueta, tarifa base, gratis-desde, restricciones de subtotal y países). Permiso `settings.shipping`. Ítem "Envíos" en el menú.
- **Seeder `ShippingSeeder`:** estándar ($99, gratis desde $999), envío gratis (desde $1500) y recoger en tienda, habilitados en todas las tiendas.
- **Tests:** `Shipping/ShippingCalculationTest` (8: tarifa fija, gratis/pickup, free_over, restricciones de subtotal y país, inactivos, tramos), `Shipping/CartShippingTest` (4: opciones, selección y total, método inválido, purga) y `Admin/ShippingManagementTest` (9: CRUD, code único/tipo, config por tienda, habilitar/deshabilitar, permisos).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **198 passed, 4 skipped** (523 assertions).

**Siguiente:** Fase 12 — Checkout (crea orden `pending_payment` + reserva stock; depende de clientes, carrito y envíos). Cierra la parte previa al pago del MVP1.

---

### 2026-06-03 — Fases 12 y 13 cerradas (Checkout + Órdenes)

**Hecho (Fase 13 — Órdenes):**
- **Esquema + modelos:** `orders` (número **incremental por website**, estado, email, totales, método de envío/pago, `placed_at`), `order_items` (snapshot de sku/nombre/precio), `order_addresses` (shipping/billing) y `order_status_histories` (de→a, comentario, usuario, notificado). 11 estados (`pending_payment`…`refunded`). `Order::transitionTo()` registra historial; `isCancellable()`.
- **Admin (`/admin/orders`):** listado con búsqueda/filtro por estado, detalle con ítems, direcciones, totales e **historial**; cambiar estado (con comentario), **comentario interno**, y **cancelar** (libera el stock reservado vía `releaseByReference`). Permisos `sales.orders.view/edit/cancel`. Ítem "Órdenes" en el menú.

**Hecho (Fase 12 — Checkout):**
- **Servicios (`app/Domain/Checkout`):** `TotalsValidator` (revalida comprable + stock), `OrderNumberGenerator` (número por sitio), `OrderDraftBuilder` (arma orden/ítems/direcciones), `PlaceOrderAction` (transacción: crea orden + ítems + direcciones, **reserva stock** `order:{id}`, marca carrito `converted`, historial inicial) y `CheckoutService` (resumen + `place`).
- **Storefront:** página de checkout (contacto, dirección de envío/facturación, **selección de envío y pago**, resumen), creación de orden en estado **`pending_payment`** con reserva de stock, y páginas de **éxito** y **pago pendiente** (acceso del invitado vía sesión). Botón "Finalizar compra" habilitado en el carrito. Pago `offline` (queda listo para conectar pasarela real en Fase 14/15).
- **Validación final** de stock y precios antes de crear la orden; el carrito se convierte y deja de estar activo.
- **Tests:** `Checkout/CheckoutTest` (7: orden pendiente, reserva de stock, conversión + historial, número por sitio, carrito vacío, validación final de stock, email requerido) y `Admin/OrderManagementTest` (9: listar/ver, cambio de estado con historial, comentario, cancelar + liberar stock, no cancelar enviada, estado inválido, enforcement).

**Verificación (todo verde):** `pint` ✓ · `tsc` ✓ · `npm run build` ✓ · suite **214 passed, 4 skipped** (574 assertions).

**Siguiente:** Fase 14 — Core de pagos (`PaymentGatewayInterface`, transacciones, webhooks, idempotencia) seguida de Fase 15 — Mercado Pago, que cierra el flujo de venta del MVP1.

---

### 2026-06-03 — Fase 25 cerrada (Emails transaccionales — subset MVP1)

**Hecho:**
- **4 notificaciones** en `app/Notifications/`:
  - `OrderCreated` — se envía al colocar la orden en el checkout, con detalle de ítems, subtotal, envío y total.
  - `PaymentApproved` — se envía cuando `PaymentService::applyToOrder` transiciona la orden a `paid`.
  - `PaymentFailed` — se envía cuando `PaymentService::applyToOrder` transiciona la orden a `failed`, con sugerencias de solución.
  - `CustomerRegistered` — se envía al crear cuenta en el storefront, con enlace a la tienda.
- **Implementación:** todas las notificaciones implementan `ShouldQueue` (encoladas por defecto contra `QUEUE_CONNECTION=database`), usan `MailMessage` (API consistente con `CustomerResetPasswordNotification`), son **multisitio-aware** (usan `$order->store->name` y `$customer->website->name` para personalizar el contenido).
- **Puntos de enganche (3 modificaciones):**
  - `CheckoutController::store()` → `OrderCreated`
  - `PaymentService::applyToOrder()` → `PaymentApproved` / `PaymentFailed` (solo en transiciones a `paid` o `failed`; no se envía para `pending` ni `refunded`).
  - `RegisterController::store()` → `CustomerRegistered`
- **Idempotencia:** los emails de pago se envían una sola vez porque `applyToOrder` retorna si la orden ya está en el estado destino (webhooks duplicados no regeneran la notificación).

**Verificación (todo verde):**
- `pint --dirty` ✓ · `tsc --noEmit` ✓ · `npm run build` ✓ · suite completa **234 passed, 4 skipped** (622 assertions). 7 tests nuevos (`TransactionalEmailsTest`).

**Siguiente:** MVP 2 — Fase 16 (Invoices/facturas internas) u otra del roadmap.

---

---

### 2026-06-03 — Fase 16 cerrada (Invoices/facturas internas)

**Hecho:**
- **Migraciones + modelos:** `invoices` (número único por website, estado `pending/cancelled/paid`, totales, fechas) e `invoice_items` (snapshot de SKU, nombre, cantidad, precios). `Invoice::STATUSES` como constantes del modelo. Facctories incluidas.
- **Servicios (`app/Domain/Sales`):** `InvoiceNumberGenerator` (secuencia por website, patrón `F-{website_code}-{seq}`), `GenerateInvoiceAction` (transacción: crea factura+ítems desde la orden, marca la orden facturada).
- **Admin `InvoiceController`:** index con filtros (estado, búsqueda) + paginación, show con detalle e ítems, store (genera factura para orden pagada/processing ya no facturada), cancel (solo `pending`, regresa orden a `paid`).
- **Permisos:** `sales.invoices.{view,create,cancel}` seedeados en roles Ventas/Super Admin/Administrador.
- **UI:** index (`admin/invoices`) con tabla, filtros y paginación; show con tabla de ítems (SKU, nombre, cantidad, precios), totales, badge de estado y botón **Cancelar** (si `pending`).
- **Integración con órdenes:** `InvoiceController::store` desde botón **Generar factura** en el sidebar del detalle de orden (visible solo si `can_invoice`). Wayfinder para todas las rutas admin.
- **8 tests** (`InvoiceManagementTest`): generar factura desde orden pagada, duplicado bloqueado, cancelación, regreso de orden a pagada, permisos, listado con filtros, vista detalle.

**Verificación (todo verde):**
- `pint --dirty` ✓ · `types:check` (solo errores preexistentes) ✓ · `npm run build` ✓ · suite **242 passed, 4 skipped** (654 assertions). 8 tests nuevos.

**Notas / decisiones:**
- Factura **interna, sin validez fiscal (CFDI)**. La facturación electrónica queda fuera del roadmap.
- `InvoiceNumberGenerator` sigue el patrón de `OrderNumberGenerator` (código de website + secuencia).
- StoreInvoiceRequest simplificado: la validación de estado (`paid`/`processing` y no ya facturada) se hace en el controller.

**Siguiente:** MVP 2 — mejoras de checkout u otra fase del roadmap.

---

### 2026-06-03 — Fase 17 cerrada (Shipments/envíos)

**Hecho:**
- **Migraciones + modelos:** `shipments` (número por website, estado pending/shipped/delivered/cancelled, carrier, tracking, total_qty, notas, fechas) e `shipment_items` (pivot con order_item_id + quantity). `Shipment::STATUSES` como constantes. Factory incluida.
- **Servicios (`app/Domain/Sales`):** `ShipmentNumberGenerator` (patrón `{code}-ENV-{seq}`), `CreateShipmentAction` (transacción: crea envío+ítems, **consume reserva de stock** — baja físico y reservado, registra movimiento, transiciona la orden).
- **Admin `ShipmentController`:** index con filtros + paginación, show con detalle e ítems, store (valida estado shippable), markShipped (guarda carrier/rastreo), markDelivered (transiciona a `complete` si todos entregados), cancel (solo pending).
- **Permisos:** `sales.shipments.{view,create,edit,cancel}` seedeados en roles Ventas/Super Admin/Administrador.
- **UI admin:** index con tabla de envíos (orden, estado, transportista, rastreo, qty, fecha), show con detalle de ítems + acciones contextuales (despachar con carrier/rastreo, marcar entregado, cancelar).
- **Orden `show.tsx`:** tabla de envíos en la timeline, formulario de **generación parcial** (selector de cantidad por ítem, respeta ya enviado).
- **Stock:** al crear envío, `consumeStockReservation` reduce `physical_qty` y `reserved_qty` y marca la reserva como `consumed` (parcial si aplica).
- **10 tests** (`ShipmentManagementTest`): generación, duplicado bloqueado, parcial, consumo de stock, marcar enviado, marcar entregado+complete, cancelar, listado, vista, permisos.

**Verificación (todo verde):**
- `pint --dirty` ✓ · `types:check` (solo errores preexistentes) ✓ · `npm run build` ✓ · suite completa **252 passed, 4 skipped** (689 assertions). 10 tests nuevos.

**Notas / decisiones:**
- Envíos pueden ser **parciales** (múltiples envíos por orden). `ShipmentNumberGenerator` incremental por website.
- Al marcar como entregado, si todos los envíos están entregados, la orden pasa a `complete`.
- Carrier y tracking se capturan al despachar (no al crear el envío), permitiendo crear el envío y luego agregar datos de rastreo.

---

## Pendientes / decisiones abiertas

- [ ] (Opcional) `sudo apt install php8.3-sqlite3` en WSL para correr `php artisan test` con el default sqlite.
- [ ] Al iniciar Fase 8: confirmar convención de carpetas frontend (`pages/admin` + `pages/storefront`, ya iniciada).
- [ ] Precios de variantes: al editar un configurable se puede ajustar precio individual de cada variante.

---

### 2026-06-03 — Fase 18 cerrada (Productos configurables)

**Hecho:**
- **Migraciones:** `add_parent_id_to_products` (FK autorreferencial, nullable) + `product_configurable_attributes` (pivot: qué atributos definen las variantes del configurable).
- **Modelo `Product`:** constante `TYPE_CONFIGURABLE`, relaciones `parent()`/`children()`/`variants()`/`configurableAttributes()`, helper `isConfigurable()`, `lowestVariantPrice()`.
- **Servicio `ConfigurableProductService`:**
  - `generateVariants()` — producto cartesiano de opciones, crea child simple por combinación con SKU `{parent}-{OPCION}-{OPCION}`, hereda stores/precios/categorías/media del padre.
  - `resolveVariant()` — lookup por `_variant_key` en JSON para PDP.
  - `getConfigurableOptions()` — atributos + opciones agrupadas para el select del front.
  - `priceForConfigurable()` — precio del variant más barato disponible.
- **Admin `ProductController`:** soporte `type=configurable` en create/edit. Al crear con `configurable_attributes` genera variantes automáticamente. `edit()` pasa `variants[]`, `configurable_attributes[]`. Index filtra `parent_id=null`.
- **Requests:** `StoreProductRequest`/`UpdateProductRequest` aceptan `type` + `configurable_attributes[]`.
- **UI admin `product-fields.tsx`:** selector tipo (simple/configurable), al elegir configurable muestra checkboxes de atributos (`is_configurable=true`), tabla de variantes generadas con SKU/opciones/precio/estado.
- **UI storefront `product.tsx`:** botones de selección por atributo configurable, resuelve variante, muestra precio/stock/galería de la variante seleccionada, botón "Agregar al carrito" usa `variant.id`. Deshabilitado si no se han seleccionado todas las opciones.
- **StorefrontController:** pasa `configurable_options` + `variants` (con precio, stock, galería por variante) al renderizar PDP de configurable.
- **11 tests** (`ConfigurableProductTest`): crear configurable, 20 variantes (5 colores × 4 tallas), SKU correctos, heredar stores/precios, resolver variante, carrito, listado, edición con variantes, borrado en cascada, 0 variantes si sin atributos.

**Verificación (todo verde):**
- `pint --dirty` ✓ · `migrate` ✓ · `wayfinder:generate` ✓ · `types:check` (solo errores preexistentes) ✓ · `npm run build` ✓ · suite completa **263 passed, 4 skipped** (749 assertions). 11 tests nuevos.

**Notas / decisiones:**
- Variantes = productos simples con `parent_id`. Stock y precios son propios de cada variante (funciona con el sistema actual de inventario/precios sin cambios).
- El precio del configurable en listados = el más barato de sus variantes activas.
- `_variant_key` en columna JSON `attributes` para resolución O(1) de variante por opciones seleccionadas.
- Al borrar un configurable, `cascadeOnDelete` elimina todas las variantes.
- Faltante para futuro: editor de precios/stock individual por variante desde la UI del padre.
