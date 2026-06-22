<?php

namespace App\Domain\Inventory;

use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class StockCsvImportService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $path): array
    {
        $parsed = $this->parse($path);
        $rows = $parsed['rows'];
        $summary = $parsed['summary'];

        $skus = collect($rows)
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values();
        $products = Product::query()
            ->whereIn('sku', $skus)
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');
        $sourceCodes = collect($rows)
            ->pluck('source_code')
            ->filter()
            ->map(fn (string $code): string => $this->targetSourceCode($code))
            ->unique()
            ->values();
        $sources = InventorySource::query()
            ->whereIn('code', $sourceCodes)
            ->get(['id', 'code', 'name'])
            ->keyBy('code');
        $stocks = InventoryStock::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('inventory_source_id', $sources->pluck('id'))
            ->get()
            ->keyBy(fn (InventoryStock $stock): string => $stock->product_id.':'.$stock->inventory_source_id);

        $seen = [];
        $previewRows = [];

        foreach ($rows as $row) {
            $errors = $row['errors'];
            $warnings = [];
            $sku = $row['sku'];
            $sourceCode = $row['source_code'];
            $targetSourceCode = $sourceCode ? $this->targetSourceCode($sourceCode) : 'default';
            $quantity = $row['quantity'];
            $product = $sku ? $products->get($sku) : null;
            $source = $targetSourceCode ? $sources->get($targetSourceCode) : null;
            $action = 'error';
            $currentQuantity = null;

            if ($sku === null || $sku === '') {
                $errors[] = 'SKU requerido.';
            }

            if ($quantity === null) {
                $errors[] = 'Cantidad requerida o invalida.';
            }

            $duplicateKey = mb_strtolower(($sourceCode ?: 'default').'|'.($sku ?: ''));

            if ($sku && isset($seen[$duplicateKey])) {
                $errors[] = 'SKU duplicado para la misma fuente.';
            }

            $seen[$duplicateKey] = true;

            if ($sku && ! $product) {
                $errors[] = 'Producto no encontrado.';
            }

            if (! $source) {
                $errors[] = "Fuente {$targetSourceCode} no existe.";
            }

            if ($errors === [] && $product && $source && $quantity !== null) {
                $stock = $stocks->get($product->id.':'.$source->id);
                $currentQuantity = $stock?->physical_qty;
                $action = match (true) {
                    $stock === null => 'create',
                    $stock->physical_qty === $quantity => 'no_change',
                    default => 'update',
                };
            }

            $previewRows[] = [
                'line' => $row['line'],
                'sku' => $sku,
                'product_name' => $product?->name,
                'source_code' => $sourceCode,
                'target_source_code' => $targetSourceCode,
                'quantity' => $quantity,
                'current_quantity' => $currentQuantity,
                'status' => $row['status'],
                'action' => $action,
                'errors' => array_values(array_unique($errors)),
                'warnings' => $warnings,
            ];
        }

        $summary = [
            ...$summary,
            'matched_skus' => collect($previewRows)->where('product_name', '!==', null)->pluck('sku')->unique()->count(),
            'missing_skus' => collect($previewRows)->filter(fn (array $row): bool => in_array('Producto no encontrado.', $row['errors'], true))->count(),
            'valid_rows' => collect($previewRows)->where('errors', [])->count(),
            'error_rows' => collect($previewRows)->filter(fn (array $row): bool => $row['errors'] !== [])->count(),
            'creates' => collect($previewRows)->where('action', 'create')->count(),
            'updates' => collect($previewRows)->where('action', 'update')->count(),
            'no_changes' => collect($previewRows)->where('action', 'no_change')->count(),
        ];

        return [
            'summary' => $summary,
            'rows' => $previewRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(string $path, ?User $user = null): array
    {
        $preview = $this->preview($path);
        $validRows = collect($preview['rows'])->where('errors', [])->values();
        $applied = 0;
        $skippedNoChange = 0;

        DB::transaction(function () use ($validRows, $user, &$applied, &$skippedNoChange): void {
            foreach ($validRows as $row) {
                if ($row['action'] === 'no_change') {
                    $skippedNoChange++;

                    continue;
                }

                $product = Product::where('sku', $row['sku'])->firstOrFail();
                $source = InventorySource::where('code', $row['target_source_code'])->firstOrFail();

                $this->stock->setPhysical(
                    $product,
                    (int) $row['quantity'],
                    $source,
                    'Importacion CSV de stock',
                    $user,
                );

                $applied++;
            }
        });

        $preview['summary']['applied'] = $applied;
        $preview['summary']['skipped_no_change'] = $skippedNoChange;

        return $preview;
    }

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function parse(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headers = null;
        $rows = [];
        $line = 0;

        foreach ($file as $record) {
            $line++;

            if ($record === [null] || $record === false) {
                continue;
            }

            $record = array_map(fn ($value): string => trim((string) $value), $record);

            if ($headers === null) {
                $headers = array_map(fn (string $header): string => mb_strtolower(trim($header)), $record);

                continue;
            }

            $row = $this->combineRow($headers, $record);
            $quantity = $this->quantity($row['quantity'] ?? null);
            $sourceCode = trim((string) ($row['source_code'] ?? ''));

            $rows[] = [
                'line' => $line,
                'sku' => $this->nullableString($row['sku'] ?? null),
                'source_code' => $sourceCode !== '' ? $sourceCode : 'default',
                'status' => $this->nullableString($row['status'] ?? null),
                'quantity' => $quantity,
                'errors' => $this->rowErrors($row, $quantity),
            ];
        }

        return [
            'summary' => [
                'total_rows' => count($rows),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $record
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $record): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $record[$index] ?? null;
        }

        return $row;
    }

    private function quantity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < 0 || floor($number) !== $number) {
            return null;
        }

        return (int) $number;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return list<string>
     */
    private function rowErrors(array $row, ?int $quantity): array
    {
        $errors = [];

        if (! array_key_exists('sku', $row)) {
            $errors[] = 'Columna sku requerida.';
        }

        if (! array_key_exists('quantity', $row)) {
            $errors[] = 'Columna quantity requerida.';
        }

        if (array_key_exists('quantity', $row) && $quantity === null) {
            $errors[] = 'Cantidad debe ser entero mayor o igual a 0.';
        }

        return $errors;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function targetSourceCode(string $sourceCode): string
    {
        return $sourceCode === 'inter' ? 'default' : $sourceCode;
    }
}
