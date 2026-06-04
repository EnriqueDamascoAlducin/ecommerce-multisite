# Plan — Mega Menú configurable desde el admin

## Requerimiento

El header público debe renderizar un menú configurable desde el admin. Cada
ítem puede apuntar a categoría, producto, página o URL libre. Cuando apunta a
una categoría y tiene `expand_products = true`, el backend adjunta los
productos activos y visibles de esa categoría para que el frontend pinte un
mega menú con cards de producto.

## Decisiones técnicas

| Decisión | Opción | Por qué |
|----------|--------|---------|
| Almacenamiento | Tabla nueva `store_header_menu_items` con `parent_id` autorreferencial | Árbol nativo, cada ítem auditable individualmente, `sort_order` para drag & drop |
| Resolución de tienda | Cada ítem tiene `store_id` con `cascadeOnDelete` | La configuración es por tienda (no por website), igual que el resto del multisitio |
| Servicio frontal | `HeaderMenuService` orquesta el armado del árbol + carga de productos | Separa la lógica del controlador y del middleware, testeable |
| Compartido al frontend | Se agrega al array `store` en `HandleInertiaRequests` via `StoreContext` | Ya existe el patrón: `store.menu` se comparte ahí |
| Productos del mega menú | Se cargan con eager loading, top 6, ordenados por `sort_order` → `name` | La categoría puede tener cientos de productos; el mega menú muestra pocos |
| Cache | (Futuro) cachear el árbol del menú por tienda | El menú se lee en cada request; con caché se evita N+1 en tiendas grandes |

---

## Estructura de datos

### Migración: `create_store_header_menu_items_table`

```php
Schema::create('store_header_menu_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('store_header_menu_items')->cascadeOnDelete();
    $table->unsignedInteger('sort_order')->default(0);
    $table->string('label');
    $table->string('link_type'); // category, product, page, url
    $table->unsignedBigInteger('ref_id')->nullable(); // category_id | product_id
    $table->string('url', 1024)->nullable();
    $table->boolean('visible')->default(true);
    $table->boolean('open_in_new_tab')->default(false);
    $table->boolean('expand_products')->default(false);
    $table->timestamps();

    $table->index(['store_id', 'parent_id', 'sort_order']);
});
```

### Modelo: `app/Models/HeaderMenuItem.php`

```php
class HeaderMenuItem extends Model
{
    protected $fillable = [
        'store_id', 'parent_id', 'sort_order', 'label', 'link_type',
        'ref_id', 'url', 'visible', 'open_in_new_tab', 'expand_products',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'expand_products' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relaciones
    public function store(): BelongsTo        → Store
    public function parent(): BelongsTo        → self
    public function children(): HasMany        → self (orderBy sort_order)
    public function category(): BelongsTo      → Category (when ref_id apunta a categoría)
    public function product(): BelongsTo       → Product  (when ref_id apunta a producto)
}
```

---

## Servicios

### `app/Domain/Store/HeaderMenuService.php`

```
buildTree(Store $store): array
├── Lee todos los ítems del store ordenados por parent_id → sort_order
├── Arma el árbol en memoria (índice por id, asigna children)
├── Itera y donde expand_products = true:
│   ├── Resuelve link_type + ref_id → categoría
│   ├── Busca productos activos, visibles en la tienda
│   ├── categoryProducts(int $categoryId, int $storeId, int $limit = 6): Collection
│   │   └── Product::whereHas('categories', $categoryId)
│   │       └── whereHas('storeLinks', store_id + is_active)
│   │       └── where('status', STATUS_ACTIVE)
│   │       └── where('visibility', '!=', 'hidden')
│   │       └── orderBy('sort_order')->orderBy('name')
│   │       └── limit(6)
│   │       └── with('media', 'prices')
│   └── Mapea cada producto a ProductCardData
│   └── Asigna array al key 'products' del ítem
└── Retorna árbol serializable para Inertia
```

### DTO para el frontend

Cada ítem se serializa como:

```json
{
  "id": 1,
  "label": "Experiencias",
  "link_type": "category",
  "ref_id": 5,
  "url": "/c/experiencias",
  "visible": true,
  "open_in_new_tab": false,
  "expand_products": true,
  "children": [],
  "products": [
    {
      "sku": "EXP-001",
      "name": "Paracaidismo",
      "slug": "paracaidismo",
      "price": { "effective_price": "2500.00", "price": "2500.00", "special_price": null, "is_special": false },
      "thumbnail": "/storage/...",
      "in_stock": true,
      "description": "Breve descripción del producto..."
    }
  ]
}
```

Los ítems sin `expand_products` simplemente no llevan key `products` (o va
vacía).

---

## Admin

### `app/Http/Controllers/Admin/HeaderMenuController.php`

| Ruta | Método | Descripción |
|------|--------|-------------|
| `admin/header-menu` | GET | Lista del árbol (cada store, con selector de store) |
| `admin/header-menu` | POST | Crear ítem raíz |
| `admin/header-menu/{item}` | PUT | Editar ítem |
| `admin/header-menu/{item}` | DELETE | Eliminar ítem (cascade children) |
| `admin/header-menu/reorder` | POST | Recibir orden de todos los ítems (drag & drop) |

### Requests

- `StoreHeaderMenuItemRequest`: validación de `label`, `link_type`, `ref_id`
  requerido según tipo, `url` requerido si type=url, `expand_products` solo
  válido si `link_type=category`.

### UI

- **Index**: árbol con indentación, drag handle para reordenar, toggle
  visible, botón agregar hijo, acciones editar/eliminar.
- **Form** (drawer o modal inline): selector de tipo, label, ref_id (select
  de categorías o productos según tipo), toggles (visible, new tab, expand
  products).
- **Permisos**: `settings.storefront` (reutilizar permiso existente de
  configuración de tienda).

---

## Frontend — Storefront

### `resources/js/layouts/storefront-layout.tsx`

Reemplazar el nav actual:

```
store.menu → array de HeaderMenuItemDTO
└── <MegaMenu items={store.menu} />
```

### `resources/js/components/storefront/mega-menu.tsx`

Lógica de render:

```
MegaMenu { items }
└── por cada item raíz:
    ├── Sin children + sin products → <Link simple>
    ├── Con children pero sin products → <Dropdown simple>
    │   └── Sub-menú vertical con links
    └── Con products → <MegaMenuDropdown>
        └── Grid de <ProductCard> (reutilizar componente existente)
```

Cada `ProductCard` necesita recibir también `description` para mostrarla en
el mega menú. Se puede extender `ProductCardData` o crear una variante.

---

## Integración con `HandleInertiaRequests`

```php
private function currentStore(): ?array
{
    // ...existing code...
    return [
        // ...existing keys...
        'menu' => $this->headerMenu($store),
    ];
}

private function headerMenu(Store $store): array
{
    return app(HeaderMenuService::class)->buildTree($store);
}
```

Se reemplaza `categoryMenu()` por `headerMenu()`.

---

## Tests

| Archivo | Pruebas |
|---------|---------|
| `tests/Feature/Storefront/MegaMenuTest.php` | Árbol buildTree, productos expandidos, límite 6, ordering, solo activos/visibles, tienda vacía sin ítems |
| `tests/Feature/Admin/HeaderMenuManagementTest.php` | CRUD ítems, reordenar, eliminar con hijos, validación por tipo, permisos |

---

## Consideraciones futuras

- **Cache**: el árbol se puede cachear por store con invalidación al crear/
  editar/eliminar un ítem.
- **Multi-idioma**: si el modelo `StoreView` se usa para i18n, el label
  podría ser traducible (deferido a fase de i18n).
- **Imagen de categoría en el menú**: si se quiere mostrar imagen de la
  categoría en el dropdown, usar el trait `HasMedia` que ya tiene
  `Category`.
