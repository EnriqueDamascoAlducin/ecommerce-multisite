<?php

namespace App\Jobs;

use App\Domain\Catalog\ProductCsvImportService;
use App\Models\ProductImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $productImportId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("product-import:{$this->productImportId}"))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(ProductCsvImportService $imports): void
    {
        $import = ProductImport::findOrFail($this->productImportId);
        $disk = Storage::disk('local');

        if (! $disk->exists($import->file_path)) {
            throw new RuntimeException('El archivo CSV de la importacion ya no existe.');
        }

        $import->update([
            'status' => ProductImport::STATUS_PROCESSING,
            'started_at' => now(),
            'error' => null,
        ]);

        $result = $imports->import(
            $disk->path($import->file_path),
            $import->user()->first(),
            function (array $progress) use ($import): void {
                $import->update([
                    'processed_products' => $progress['processed_products'],
                    'processed_images' => $progress['processed_images'],
                ]);
            },
        );

        $import->update([
            'status' => ProductImport::STATUS_COMPLETED,
            'processed_products' => $import->total_products,
            'processed_images' => $import->total_images,
            'summary' => $result['summary'],
            'result' => $result,
            'completed_at' => now(),
        ]);

        $disk->delete($import->file_path);
    }

    public function failed(?Throwable $exception): void
    {
        $import = ProductImport::find($this->productImportId);

        if (! $import) {
            return;
        }

        $import->update([
            'status' => ProductImport::STATUS_FAILED,
            'error' => $exception?->getMessage() ?: 'La importacion fallo.',
            'completed_at' => now(),
        ]);

        Storage::disk('local')->delete($import->file_path);
    }
}
