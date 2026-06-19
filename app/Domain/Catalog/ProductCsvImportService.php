<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\StockService;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductStore;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

class ProductCsvImportService
{
    /** @var list<string> */
    private const STATUSES = [Product::STATUS_ACTIVE, Product::STATUS_INACTIVE];

    /** @var list<string> */
    private const VISIBILITIES = ['both', 'catalog', 'search', 'hidden'];

    /** @var array<string, string> */
    private const MAGENTO_VISIBILITIES = [
        'catalog, search' => 'both',
        'catalog' => 'catalog',
        'search' => 'search',
        'not visible individually' => 'hidden',
    ];

    /** @var array<string, string> */
    private const MAGENTO_STORE_VIEW_STORES = [
        'view_inte' => 'Interferenciales',
        'view_soph' => 'Interferenciales Sports',
        'view_reah' => 'Veterinaria',
    ];

    /** @var array<string, string> */
    private const MAGENTO_MEDIA_BASE_URLS = [
        'Interferenciales' => 'https://interferenciales.com.mx/media/catalog/product',
        'Interferenciales Sports' => 'https://interferenciales.com.mx/media/catalog/product',
        'Veterinaria' => 'https://rehabilitacionveterinaria.com.mx/media/catalog/product',
    ];

    /** @var Collection<int, Category>|null */
    private ?Collection $categoryTree = null;

    /** @var Collection<string, Store>|null */
    private ?Collection $magentoStores = null;

    public function __construct(
        private readonly StockService $stockService,
        private readonly ProductImageImportService $imageImporter,
    ) {}

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    public function preview(string $path): array
    {
        return $this->publicResult($this->buildResult($path));
    }

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    /**
     * @param  (callable(array{processed_products: int, processed_images: int}): void)|null  $progress
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    public function import(string $path, ?User $user = null, ?callable $progress = null): array
    {
        $result = $this->buildResult($path);
        $processedProducts = 0;

        foreach ($result['rows'] as &$row) {
            if ($row['action'] === 'skip' || $row['errors'] !== []) {
                continue;
            }

            DB::transaction(fn () => $this->persistRow($row['data'], $user));
            $result['summary']['imported']++;
            $processedProducts++;
            if ($progress) {
                $progress([
                    'processed_products' => $processedProducts,
                    'processed_images' => 0,
                ]);
            }
        }

        unset($row);

        $this->importProductImages($result, $user, $progress, $processedProducts);
        $result['summary']['skipped'] = $result['summary']['error_rows'];

        return $this->publicResult($result);
    }

    /**
     * @param  array{summary: array<string, int>, rows: list<array<string, mixed>>}  $result
     * @param  (callable(array{processed_products: int, processed_images: int}): void)|null  $progress
     */
    private function importProductImages(
        array &$result,
        ?User $user,
        ?callable $progress,
        int $processedProducts,
    ): void {
        $processedImages = 0;

        foreach ($result['rows'] as &$row) {
            if ($row['action'] === 'skip' || $row['errors'] !== [] || ($row['data']['images'] ?? []) === []) {
                continue;
            }

            $product = Product::where('sku', $row['data']['sku'])->first();

            if (! $product) {
                continue;
            }

            $images = $this->imageImporter->import(
                $product,
                $row['data']['images'],
                $row['data']['image_base_urls'],
                $user,
            );

            $result['summary']['images_downloaded'] += $images['downloaded'];
            $result['summary']['images_reused'] += $images['reused'];
            $result['summary']['images_failed'] += $images['failed'];
            $row['warnings'] = array_merge($row['warnings'], $images['warnings']);
            $processedImages += $images['downloaded'] + $images['reused'] + $images['failed'];
            if ($progress) {
                $progress([
                    'processed_products' => $processedProducts,
                    'processed_images' => $processedImages,
                ]);
            }
        }

        unset($row);
    }

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildResult(string $path): array
    {
        $parsed = $this->parseCsv($path);
        $isMagento = $this->isMagentoExport($parsed['headers']);

        if ($isMagento) {
            return $this->buildMagentoResult($parsed);
        }

        $rows = [];

        $summary = [
            'total_rows' => 0,
            'valid_rows' => 0,
            'error_rows' => 0,
            'skipped_rows' => 0,
            'assigned_store_views' => 0,
            'omitted_store_views' => 0,
            'omitted_unsupported_types' => 0,
            'missing_categories' => 0,
            'images_detected' => 0,
            'images_downloaded' => 0,
            'images_reused' => 0,
            'images_failed' => 0,
            'creates' => 0,
            'updates' => 0,
            'imported' => 0,
            'skipped' => 0,
        ];

        foreach ($parsed['rows'] as $csvRow) {
            $summary['total_rows']++;
            $prepared = $isMagento
                ? $this->prepareMagentoRow($csvRow['values'])
                : ['row' => $csvRow['values'], 'skip_reason' => null, 'skip_kind' => null];

            if ($prepared['skip_reason'] !== null) {
                $summary['skipped_rows']++;

                if ($prepared['skip_kind'] === 'store_view') {
                    $summary['omitted_store_views']++;
                }

                if ($prepared['skip_kind'] === 'unsupported_type') {
                    $summary['omitted_unsupported_types']++;
                }

                $rows[] = [
                    'line' => $csvRow['line'],
                    'sku' => $csvRow['values']['sku'] ?? null,
                    'name' => $csvRow['values']['name'] ?? null,
                    'action' => 'skip',
                    'errors' => [],
                    'warnings' => [$prepared['skip_reason']],
                    'data' => [],
                ];

                continue;
            }

            $validated = $this->validateRow($prepared['row'], $parsed['headers'], $isMagento);
            $summary['missing_categories'] += $validated['missing_categories'];
            $summary['images_detected'] += count($validated['data']['images']);

            if ($validated['errors'] === []) {
                $summary['valid_rows']++;
                $summary[$validated['action'] === 'update' ? 'updates' : 'creates']++;
            } else {
                $summary['error_rows']++;
            }

            $rows[] = [
                'line' => $csvRow['line'],
                'sku' => $csvRow['values']['sku'] ?? null,
                'name' => $csvRow['values']['name'] ?? null,
                'action' => $validated['action'],
                'errors' => $validated['errors'],
                'warnings' => $validated['warnings'],
                'data' => $validated['data'],
            ];
        }

        return compact('summary', 'rows');
    }

    /**
     * @param  array{headers: list<string>, rows: list<array{line: int, values: array<string, string>}>}  $parsed
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildMagentoResult(array $parsed): array
    {
        $rows = [];
        $groups = collect($parsed['rows'])->groupBy(fn (array $row): string => trim($row['values']['sku'] ?? ''));

        $summary = [
            'total_rows' => count($parsed['rows']),
            'valid_rows' => 0,
            'error_rows' => 0,
            'skipped_rows' => 0,
            'assigned_store_views' => 0,
            'omitted_store_views' => 0,
            'omitted_unsupported_types' => 0,
            'missing_categories' => 0,
            'images_detected' => 0,
            'images_downloaded' => 0,
            'images_reused' => 0,
            'images_failed' => 0,
            'creates' => 0,
            'updates' => 0,
            'imported' => 0,
            'skipped' => 0,
        ];

        foreach ($groups as $sku => $group) {
            $baseRow = $group->first(fn (array $row): bool => trim($row['values']['store_view_code'] ?? '') === '');
            $storeViewRows = $group->filter(fn (array $row): bool => trim($row['values']['store_view_code'] ?? '') !== '');

            if (! $baseRow) {
                foreach ($storeViewRows as $storeViewRow) {
                    $summary['skipped_rows']++;
                    $summary['omitted_store_views']++;
                    $rows[] = $this->skippedRow($storeViewRow, 'Store view omitida porque no existe fila base del SKU.');
                }

                continue;
            }

            $prepared = $this->prepareMagentoRow(
                $baseRow['values'],
                $storeViewRows->pluck('values')->all(),
                $summary,
            );

            if ($prepared['skip_reason'] !== null) {
                $summary['skipped_rows']++;

                if ($prepared['skip_kind'] === 'unsupported_type') {
                    $summary['omitted_unsupported_types']++;
                }

                $rows[] = $this->skippedRow($baseRow, $prepared['skip_reason']);

                foreach ($storeViewRows as $storeViewRow) {
                    $summary['skipped_rows']++;
                    $summary['omitted_store_views']++;
                    $rows[] = $this->skippedRow($storeViewRow, 'Store view omitida porque la fila base no es simple.');
                }

                continue;
            }

            $validated = $this->validateRow($prepared['row'], $parsed['headers'], true);
            $summary['missing_categories'] += $validated['missing_categories'];
            $summary['images_detected'] += count($validated['data']['images']);

            if ($validated['errors'] === []) {
                $summary['valid_rows']++;
                $summary[$validated['action'] === 'update' ? 'updates' : 'creates']++;
            } else {
                $summary['error_rows']++;
            }

            $warnings = array_merge($prepared['warnings'], $validated['warnings']);

            $rows[] = [
                'line' => $baseRow['line'],
                'sku' => $baseRow['values']['sku'] ?? null,
                'name' => $baseRow['values']['name'] ?? null,
                'action' => $validated['action'],
                'errors' => $validated['errors'],
                'warnings' => $warnings,
                'data' => $validated['data'],
            ];
        }

        return compact('summary', 'rows');
    }

    /**
     * @param  array{line: int, values: array<string, string>}  $row
     * @return array<string, mixed>
     */
    private function skippedRow(array $row, string $reason): array
    {
        return [
            'line' => $row['line'],
            'sku' => $row['values']['sku'] ?? null,
            'name' => $row['values']['name'] ?? null,
            'action' => 'skip',
            'errors' => [],
            'warnings' => [$reason],
            'data' => [],
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: array<string, string>}>}
     */
    private function parseCsv(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = [];
        $rows = [];
        $isMagento = false;

        foreach ($file as $line => $values) {
            if ($values === false || $values === [null] || ! is_array($values)) {
                continue;
            }

            $values = array_map(fn ($value) => trim((string) $value), $values);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            if ($headers === []) {
                $headers = $this->normalizeHeaders($values);
                $isMagento = $this->isMagentoExport($headers);

                continue;
            }

            if ($isMagento && count($values) !== count($headers)) {
                if (! $this->isMagentoDataRow($values)) {
                    continue;
                }

                $values = $this->truncateMagentoPartialRow($values, $headers);
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? '';
            }

            $rows[] = ['line' => $line + 1, 'values' => $row];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headers
     */
    private function isMagentoExport(array $headers): bool
    {
        return collect(['store_view_code', 'product_type', 'product_online', 'qty', 'categories'])
            ->every(fn (string $header) => in_array($header, $headers, true));
    }

    /**
     * @param  list<string>  $values
     */
    private function isMagentoDataRow(array $values): bool
    {
        return in_array(trim($values[3] ?? ''), [
            Product::TYPE_SIMPLE,
            Product::TYPE_CONFIGURABLE,
            Product::TYPE_BUNDLE,
            Product::TYPE_DOWNLOADABLE,
        ], true);
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function truncateMagentoPartialRow(array $values, array $headers): array
    {
        $cutoff = array_search('additional_attributes', $headers, true);

        if ($cutoff === false) {
            return $values;
        }

        foreach (array_keys($headers) as $index) {
            if ($index >= $cutoff) {
                $values[$index] = '';
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array<string, string>>  $storeViewRows
     * @param  array<string, int>  $summary
     * @return array{row: array<string, string>, skip_reason: string|null, skip_kind: string|null, warnings: list<string>}
     */
    private function prepareMagentoRow(array $row, array $storeViewRows = [], array &$summary = []): array
    {
        $storeView = trim($row['store_view_code'] ?? '');

        if ($storeView !== '') {
            return [
                'row' => $row,
                'skip_reason' => "Fila de store view omitida ({$storeView}).",
                'skip_kind' => 'store_view',
                'warnings' => [],
            ];
        }

        $type = trim($row['product_type'] ?? '');

        if ($type !== Product::TYPE_SIMPLE) {
            return [
                'row' => $row,
                'skip_reason' => "Tipo de producto {$type} omitido en esta fase.",
                'skip_kind' => 'unsupported_type',
                'warnings' => [],
            ];
        }

        $visibility = Str::of($row['visibility'] ?? '')->lower()->toString();

        $row['status'] = trim($row['product_online'] ?? '') === '1'
            ? Product::STATUS_ACTIVE
            : Product::STATUS_INACTIVE;
        $row['visibility'] = self::MAGENTO_VISIBILITIES[$visibility] ?? ($row['visibility'] ?? '');
        $row['stock_qty'] = $this->normalizeMagentoQuantity($row['qty'] ?? '');
        $row['special_price_from'] = $this->normalizeMagentoDate($row['special_price_from_date'] ?? '');
        $row['special_price_to'] = $this->normalizeMagentoDate($row['special_price_to_date'] ?? '');
        $row['category_paths'] = $row['categories'] ?? '';
        $row['store_codes'] = $this->mappedMagentoStoreViewCodes($storeViewRows, $summary);

        $mappedCount = count($this->splitList($row['store_codes']));
        $unmappedCount = collect($storeViewRows)
            ->filter(fn (array $storeViewRow): bool => ! isset(self::MAGENTO_STORE_VIEW_STORES[trim($storeViewRow['store_view_code'] ?? '')]))
            ->count();
        $warnings = [];

        if ($mappedCount > 0) {
            $warnings[] = "{$mappedCount} store view(s) asociada(s) a tiendas.";
        }

        if ($unmappedCount > 0) {
            $warnings[] = "{$unmappedCount} store view(s) sin match omitida(s).";
        }

        return ['row' => $row, 'skip_reason' => null, 'skip_kind' => null, 'warnings' => $warnings];
    }

    /**
     * @param  list<array<string, string>>  $storeViewRows
     * @param  array<string, int>  $summary
     */
    private function mappedMagentoStoreViewCodes(array $storeViewRows, array &$summary): string
    {
        $storeNames = [];

        foreach ($storeViewRows as $storeViewRow) {
            $storeView = trim($storeViewRow['store_view_code'] ?? '');

            if (isset(self::MAGENTO_STORE_VIEW_STORES[$storeView])) {
                $summary['assigned_store_views'] = ($summary['assigned_store_views'] ?? 0) + 1;
                $storeNames[] = self::MAGENTO_STORE_VIEW_STORES[$storeView];

                continue;
            }

            $summary['omitted_store_views'] = ($summary['omitted_store_views'] ?? 0) + 1;
            $summary['skipped_rows'] = ($summary['skipped_rows'] ?? 0) + 1;
        }

        return collect($storeNames)->unique()->implode(',');
    }

    /**
     * @param  array<string, string>  $row
     * @return list<array{path: string, label: string|null, primary: bool}>
     */
    private function magentoImages(array $row): array
    {
        $images = [];
        $baseImage = trim($row['base_image'] ?? '');

        if ($baseImage !== '' && $baseImage !== 'no_selection') {
            $images[$baseImage] = [
                'path' => $baseImage,
                'label' => $this->nullableString($row['base_image_label'] ?? ''),
                'primary' => true,
            ];
        }

        $additionalImages = $this->splitList($row['additional_images'] ?? '');
        $additionalLabels = array_map('trim', explode(',', $row['additional_image_labels'] ?? ''));

        foreach ($additionalImages as $index => $path) {
            if ($path === 'no_selection' || isset($images[$path])) {
                continue;
            }

            $images[$path] = [
                'path' => $path,
                'label' => $this->nullableString($additionalLabels[$index] ?? ''),
                'primary' => false,
            ];
        }

        return array_values($images);
    }

    /**
     * @return list<string>
     */
    private function magentoImageBaseUrls(string $storeNames): array
    {
        $urls = collect($this->splitList($storeNames))
            ->map(fn (string $storeName): ?string => self::MAGENTO_MEDIA_BASE_URLS[$storeName] ?? null)
            ->filter()
            ->values();

        $urls->push(self::MAGENTO_MEDIA_BASE_URLS['Interferenciales']);

        return $urls->unique()->values()->all();
    }

    private function normalizeMagentoQuantity(string $value): string
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $quantity = (float) $value;

        return floor($quantity) === $quantity ? (string) (int) $quantity : $value;
    }

    private function normalizeMagentoDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeHeaders(array $values): array
    {
        return array_map(function (string $header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

            return Str::of($header)->trim()->lower()->toString();
        }, $values);
    }

    /**
     * @param  list<string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (string $value) => trim($value) === '');
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @return array{action: string, errors: list<string>, warnings: list<string>, missing_categories: int, data: array<string, mixed>}
     */
    private function validateRow(array $row, array $headers, bool $isMagento = false): array
    {
        $errors = [];
        $warnings = [];
        $missingCategories = 0;
        $sku = trim($row['sku'] ?? '');
        $name = trim($row['name'] ?? '');
        $type = $this->firstFilled($row, ['product_type', 'type']) ?: Product::TYPE_SIMPLE;
        $status = trim($row['status'] ?? '') ?: Product::STATUS_INACTIVE;
        $visibility = trim($row['visibility'] ?? '') ?: 'both';
        $existing = $sku !== '' ? Product::where('sku', $sku)->first() : null;

        if ($sku === '') {
            $errors[] = 'SKU es obligatorio.';
        }

        if ($name === '') {
            $errors[] = 'Nombre es obligatorio.';
        }

        if (! array_key_exists('price', $row) || trim($row['price']) === '') {
            $errors[] = 'Precio es obligatorio.';
        } elseif (! is_numeric($row['price']) || (float) $row['price'] < 0) {
            $errors[] = 'Precio debe ser numerico y mayor o igual a 0.';
        }

        if ($type !== Product::TYPE_SIMPLE) {
            $errors[] = 'Solo se soportan productos simple en esta version.';
        }

        if ($existing && $existing->type !== Product::TYPE_SIMPLE) {
            $errors[] = 'El SKU existe, pero no corresponde a un producto simple.';
        }

        if (! in_array($status, self::STATUSES, true)) {
            $errors[] = 'Estado invalido. Usa active o inactive.';
        }

        if (! in_array($visibility, self::VISIBILITIES, true)) {
            $errors[] = 'Visibilidad invalida. Usa both, catalog, search o hidden.';
        }

        foreach (['weight', 'special_price'] as $field) {
            if (($row[$field] ?? '') !== '' && ! is_numeric($row[$field])) {
                $errors[] = "{$field} debe ser numerico.";
            }
        }

        $stores = $isMagento
            ? $this->resolveMagentoStores($row['store_codes'] ?? '', $errors)
            : $this->resolveStores($row['store_codes'] ?? '', $errors);
        $categories = $this->resolveCategories($row, $errors, $isMagento, $missingCategories);

        if ($missingCategories > 0) {
            $warnings[] = "{$missingCategories} categoria(s) no encontrada(s); no se asignaron.";
        }

        $source = $this->resolveInventorySource($row['inventory_source_code'] ?? '', $errors);
        $stockQty = $this->resolveStockQty($row['stock_qty'] ?? '', $errors);
        $attributes = $this->resolveAttributes($row, $headers, $errors);
        $storePrices = $this->resolveStorePrices($row, $headers, $errors);

        return [
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'missing_categories' => $missingCategories,
            'data' => [
                'sku' => $sku,
                'name' => $name,
                'slug' => $this->firstFilled($row, ['url_key', 'slug']),
                'short_description' => $row['short_description'] ?? null,
                'description' => $row['description'] ?? null,
                'status' => $status,
                'visibility' => $visibility,
                'weight' => $this->nullableString($row['weight'] ?? ''),
                'price' => $this->nullableString($row['price'] ?? ''),
                'special_price' => $this->nullableString($row['special_price'] ?? ''),
                'special_price_from' => $this->nullableString($row['special_price_from'] ?? ''),
                'special_price_to' => $this->nullableString($row['special_price_to'] ?? ''),
                'images' => $isMagento ? $this->magentoImages($row) : [],
                'image_base_urls' => $isMagento ? $this->magentoImageBaseUrls($row['store_codes'] ?? '') : [],
                'stores' => $stores,
                'categories' => $categories,
                'inventory_source' => $source,
                'stock_qty' => $stockQty,
                'attributes' => $attributes,
                'store_prices' => $storePrices,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $fields
     */
    private function firstFilled(array $row, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (trim($row[$field] ?? '') !== '') {
                return trim($row[$field]);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $errors
     * @return Collection<int, Store>
     */
    private function resolveMagentoStores(string $storeNames, array &$errors): Collection
    {
        $stores = $this->magentoStores();
        $names = collect(array_merge(['Interferenciales'], $this->splitList($storeNames)))
            ->unique()
            ->values();
        $resolved = collect();

        foreach ($names as $name) {
            $store = $stores->get($name);

            if (! $store) {
                $errors[] = "No se encontro la tienda Magento {$name}.";

                continue;
            }

            $resolved->push($store);
        }

        return $resolved->unique('id')->values();
    }

    /**
     * @return Collection<string, Store>
     */
    private function magentoStores(): Collection
    {
        if ($this->magentoStores !== null) {
            return $this->magentoStores;
        }

        $names = array_values(array_unique(array_merge(['Interferenciales'], array_values(self::MAGENTO_STORE_VIEW_STORES))));

        return $this->magentoStores = Store::query()
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $errors
     * @return Collection<int, Store>
     */
    private function resolveStores(string $codes, array &$errors): Collection
    {
        $items = $this->splitList($codes);

        if ($items === []) {
            return collect();
        }

        $stores = Store::whereIn('code', $items)->get()->keyBy('code');

        foreach ($items as $code) {
            if (! $stores->has($code)) {
                $errors[] = "La tienda {$code} no existe.";
            }
        }

        return $stores->values();
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $errors
     * @return Collection<int, Category>
     */
    private function resolveCategories(array $row, array &$errors, bool $ignoreMissing = false, int &$missing = 0): Collection
    {
        $categories = collect();
        $ids = array_map('intval', $this->splitList($row['category_ids'] ?? ''));

        if ($ids !== []) {
            $found = Category::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                if (! $found->has($id)) {
                    $missing++;

                    if (! $ignoreMissing) {
                        $errors[] = "La categoria {$id} no existe.";
                    }
                }
            }

            $categories = $categories->merge($found->values());
        }

        foreach ($this->splitList($row['category_paths'] ?? '') as $path) {
            $category = $this->findCategoryByPath($path);

            if (! $category) {
                $missing++;

                if (! $ignoreMissing) {
                    $errors[] = "La ruta de categoria {$path} no existe.";
                }

                continue;
            }

            $categories->push($category);
        }

        return $categories->unique('id')->values();
    }

    private function findCategoryByPath(string $path): ?Category
    {
        $parts = collect(preg_split('/\s*(?:\/|>)\s*/', $path) ?: [])
            ->map(fn (string $part) => Str::of($part)->lower()->toString())
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        $categories = $this->categoryTree ??= Category::query()
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        return $categories->first(function (Category $category) use ($parts, $categories) {
            $chain = collect();
            $current = $category;

            while ($current) {
                $chain->prepend(Str::of($current->name)->lower()->toString());
                $current = $current->parent_id ? $categories->get($current->parent_id) : null;
            }

            return $chain->values()->all() === $parts->all();
        });
    }

    /**
     * @param  list<string>  $errors
     */
    private function resolveInventorySource(string $code, array &$errors): ?InventorySource
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $source = InventorySource::where('code', $code)->first();

        if (! $source) {
            $errors[] = "La fuente de inventario {$code} no existe.";
        }

        return $source;
    }

    /**
     * @param  list<string>  $errors
     */
    private function resolveStockQty(string $value, array &$errors): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            $errors[] = 'stock_qty debe ser un entero mayor o igual a 0.';

            return null;
        }

        $quantity = (float) $value;

        if ($quantity < 0 || floor($quantity) !== $quantity) {
            $errors[] = 'stock_qty debe ser un entero mayor o igual a 0.';

            return null;
        }

        return (int) $quantity;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @param  list<string>  $errors
     * @return list<array{attribute: Attribute, value: string|null}>
     */
    private function resolveAttributes(array $row, array $headers, array &$errors): array
    {
        $values = [];

        foreach ($headers as $header) {
            if (! Str::startsWith($header, 'attribute:')) {
                continue;
            }

            $code = Str::after($header, 'attribute:');
            $attribute = Attribute::with('options')->where('code', $code)->first();

            if (! $attribute) {
                $errors[] = "El atributo {$code} no existe.";

                continue;
            }

            $rawValue = trim($row[$header] ?? '');

            if ($rawValue === '') {
                $values[] = ['attribute' => $attribute, 'value' => null];

                continue;
            }

            if ($attribute->hasOptions()) {
                $resolved = $this->resolveAttributeOptionValue($attribute, $rawValue, $errors);
                $values[] = ['attribute' => $attribute, 'value' => $resolved];

                continue;
            }

            $values[] = ['attribute' => $attribute, 'value' => $rawValue];
        }

        return $values;
    }

    /**
     * @param  list<string>  $errors
     */
    private function resolveAttributeOptionValue(Attribute $attribute, string $value, array &$errors): ?string
    {
        $items = $attribute->type === Attribute::TYPE_MULTISELECT ? $this->splitList($value) : [$value];
        $resolved = [];
        $options = $attribute->options->keyBy(fn (AttributeOption $option) => Str::of($option->value)->lower()->toString());
        $labels = $attribute->options->keyBy(fn (AttributeOption $option) => Str::of($option->label)->lower()->toString());

        foreach ($items as $item) {
            $key = Str::of($item)->lower()->toString();
            $option = $options->get($key) ?? $labels->get($key);

            if (! $option) {
                $errors[] = "La opcion {$item} no existe para el atributo {$attribute->code}.";

                continue;
            }

            $resolved[] = $option->value;
        }

        if ($attribute->type === Attribute::TYPE_MULTISELECT) {
            return json_encode($resolved) ?: null;
        }

        return $resolved[0] ?? null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @param  list<string>  $errors
     * @return list<array{store: Store, price: string, special_price: string|null}>
     */
    private function resolveStorePrices(array $row, array $headers, array &$errors): array
    {
        $values = [];

        foreach ($headers as $header) {
            if (! Str::startsWith($header, 'store_price:') || trim($row[$header] ?? '') === '') {
                continue;
            }

            $code = Str::after($header, 'store_price:');
            $store = Store::where('code', $code)->first();

            if (! $store) {
                $errors[] = "La tienda {$code} no existe para precio por tienda.";

                continue;
            }

            if (! is_numeric($row[$header]) || (float) $row[$header] < 0) {
                $errors[] = "store_price:{$code} debe ser numerico y mayor o igual a 0.";

                continue;
            }

            $specialHeader = "store_special_price:{$code}";
            $specialPrice = trim($row[$specialHeader] ?? '');

            if ($specialPrice !== '' && ! is_numeric($specialPrice)) {
                $errors[] = "store_special_price:{$code} debe ser numerico.";
            }

            $values[] = [
                'store' => $store,
                'price' => $row[$header],
                'special_price' => $specialPrice === '' ? null : $specialPrice,
            ];
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRow(array $data, ?User $user): Product
    {
        $product = Product::where('sku', $data['sku'])->first();
        $isNew = ! $product;

        $payload = [
            'type' => Product::TYPE_SIMPLE,
            'price_type' => null,
            'parent_id' => null,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'short_description' => $data['short_description'],
            'description' => $data['description'],
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'weight' => $data['weight'],
        ];

        if ($isNew || $data['slug']) {
            $payload['slug'] = $this->uniqueSlug($data['slug'], $data['name'], $product?->id);
        }

        $product = $product
            ? tap($product)->update($payload)
            : Product::create($payload);

        ProductPrice::updateOrCreate(
            ['product_id' => $product->id, 'store_id' => null],
            [
                'price' => $data['price'],
                'special_price' => $data['special_price'],
                'special_price_from' => $data['special_price_from'],
                'special_price_to' => $data['special_price_to'],
            ],
        );

        foreach ($data['stores'] as $store) {
            ProductStore::updateOrCreate(
                ['product_id' => $product->id, 'store_id' => $store->id],
                ['is_active' => true, 'visibility' => null],
            );
        }

        foreach ($data['store_prices'] as $storePrice) {
            ProductPrice::updateOrCreate(
                ['product_id' => $product->id, 'store_id' => $storePrice['store']->id],
                ['price' => $storePrice['price'], 'special_price' => $storePrice['special_price']],
            );
        }

        if ($data['categories']->isNotEmpty()) {
            $product->categories()->sync($data['categories']->pluck('id')->all());
        }

        foreach ($data['attributes'] as $attributeValue) {
            if ($attributeValue['value'] === null) {
                ProductAttributeValue::where('product_id', $product->id)
                    ->where('attribute_id', $attributeValue['attribute']->id)
                    ->delete();

                continue;
            }

            ProductAttributeValue::updateOrCreate(
                ['product_id' => $product->id, 'attribute_id' => $attributeValue['attribute']->id],
                ['value' => $attributeValue['value']],
            );
        }

        if ($data['stock_qty'] !== null) {
            $this->stockService->setPhysical($product, $data['stock_qty'], $data['inventory_source'], 'Importacion CSV', $user);
        }

        return $product;
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name) ?: Str::random(8);
        $candidate = $base;
        $counter = 2;

        while (Product::where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * @param  array{summary: array<string, int>, rows: list<array<string, mixed>>}  $result
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function publicResult(array $result): array
    {
        $result['rows'] = array_map(fn (array $row): array => [
            'line' => $row['line'],
            'sku' => $row['sku'],
            'name' => $row['name'],
            'action' => $row['action'],
            'errors' => $row['errors'],
            'warnings' => $row['warnings'],
        ], $result['rows']);

        return $result;
    }
}
