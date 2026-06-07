# Plan: Buscador Público de Productos con Filtros Magento-Like

## Summary

Agregar buscador en header storefront para buscar productos por `sku` o `name`, llevando a una nueva página pública `/buscar`. Resultados tendrán sidebar de filtros por atributos `is_filterable`, respetando tienda actual, visibilidad pública, stock/precio existente y navegación Inertia.

## Key Changes

- Crear ruta storefront `GET /buscar` y `GET /{store_code}/buscar` antes del catch-all CMS; agregar `buscar` a slugs reservados de páginas.
- Agregar método `search()` en `StorefrontController`:
  - query param `q` busca por `products.name` o `products.sku`.
  - filtros `attrs[{attribute_id}]` reutilizan semántica admin: select/text contains, multiselect contains opción, boolean exacto, number `min/max`, date `from/to`.
  - resultados solo productos activos, vinculados a tienda actual, con visibilidad `both` o `search`.
  - paginación con `withQueryString()` y cards usando `productCard()`.
- Exponer `filterOptions.attributes` con atributos `is_filterable`, ordenados por `sort_order/name`, incluyendo opciones para select/multiselect.
- Crear página Inertia `storefront/search.tsx`:
  - título “Buscar productos”.
  - muestra query actual, contador, grid de `ProductCard`.
  - sidebar desktop con filtros; panel colapsable en móvil.
  - botón “Limpiar filtros” conserva `q` o vuelve a `/buscar`.
- Agregar componente de búsqueda en `StorefrontLayout`:
  - input en header con icono search, placeholder “Buscar por nombre o SKU”.
  - submit GET a `urls.search()` con `q`.
  - en móvil ocupa una segunda fila bajo logo/actions.
  - usar `useStoreUrls()` agregando `search: () => `${prefix}/buscar``.
- Regenerar Wayfinder si se agregan rutas tipadas usadas por frontend.

## Public Interfaces

- URL pública: `/buscar?q=texto`.
- Con prefijo tienda: `/{store_code}/buscar?q=texto`.
- Filtros:
  - `attrs[12]=red`
  - `attrs[14][min]=100&attrs[14][max]=500`
  - `attrs[18][from]=2026-01-01&attrs[18][to]=2026-12-31`
- Inertia props:
  - `filters: { q: string, attrs: Record<string, unknown> }`
  - `filterOptions.attributes`
  - `products` paginado con shape de `ProductCardData`.

## Test Plan

- Feature storefront:
  - busca por nombre.
  - busca por SKU.
  - excluye productos no activos, no vinculados a tienda, o con visibilidad `catalog/hidden`.
  - incluye productos con visibilidad `both` y `search`.
  - filtra por atributo `is_filterable` select, boolean, number range y text.
  - ignora filtros de atributos no filtrables.
  - conserva query string en paginación.
  - `/c/{slug}` y `/p/{slug}` siguen resolviendo antes que `/buscar`.
- Frontend/checks:
  - `npm run types:check`.
  - Prettier en archivos tocados.
  - Verificar desktop/mobile header: input no rompe logo, cuenta ni carrito.
- PHP:
  - `php artisan test --compact tests/Feature/Storefront/StorefrontSearchTest.php`.
  - Si sigue faltando `pdo_sqlite`, documentar bloqueo de entorno.

## Assumptions

- V1 no incluye autocomplete; solo página de resultados con filtros.
- Filtros visibles solo usan `attributes.is_filterable = true`.
- No migraciones ni índice full-text en v1; búsqueda usa `LIKE` como el admin actual.
- Resultados de búsqueda usan visibilidad `both` o `search`; categoría sigue usando `both` o `catalog`.
