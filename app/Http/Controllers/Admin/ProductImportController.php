<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\ProductCsvImportService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductCsvImportService $imports,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Request $request): Response
    {
        $latestImport = ProductImport::query()
            ->where('user_id', $request->user()?->id)
            ->latest()
            ->first();
        $result = session('product_import_result');

        if (! $result && $latestImport?->status === ProductImport::STATUS_COMPLETED) {
            $result = $latestImport->result;
        }

        return Inertia::render('admin/products/import', [
            'result' => $result,
            'token' => session('product_import_token'),
            'activeImport' => $this->presentImport($latestImport),
        ]);
    }

    public function validateUpload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $token = (string) Str::uuid();
        $path = $data['file']->storeAs('imports/products', "{$token}.csv", 'local');
        $result = $this->imports->preview(Storage::disk('local')->path($path));

        return to_route('admin.products.import.create')
            ->with('product_import_result', $result)
            ->with('product_import_token', $token);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $hasActiveImport = ProductImport::query()
            ->where('user_id', $request->user()?->id)
            ->whereIn('status', [ProductImport::STATUS_PENDING, ProductImport::STATUS_PROCESSING])
            ->exists();

        if ($hasActiveImport) {
            throw ValidationException::withMessages([
                'token' => 'Ya hay una importacion de productos en proceso.',
            ]);
        }

        $path = "imports/products/{$data['token']}.csv";
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $preview = $this->imports->preview($disk->path($path));
        $import = ProductImport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'file_path' => $path,
            'status' => ProductImport::STATUS_PENDING,
            'total_products' => $preview['summary']['valid_rows'],
            'total_images' => $preview['summary']['images_detected'],
            'summary' => $preview['summary'],
        ]);

        ProcessProductImport::dispatch($import->id);

        $this->auditLogger->log(
            'product.import.queued',
            $import,
            'Importacion CSV de productos enviada a segundo plano',
            ['summary' => $preview['summary']],
        );

        return to_route('admin.products.import.create')
            ->with('success', 'Importacion enviada a segundo plano.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentImport(?ProductImport $import): ?array
    {
        if (! $import) {
            return null;
        }

        $total = $import->total_products + $import->total_images;
        $processed = min($total, $import->processed_products + $import->processed_images);

        return [
            'id' => $import->id,
            'uuid' => $import->uuid,
            'status' => $import->status,
            'total_products' => $import->total_products,
            'processed_products' => $import->processed_products,
            'total_images' => $import->total_images,
            'processed_images' => $import->processed_images,
            'progress' => $total > 0 ? (int) floor(($processed / $total) * 100) : 0,
            'summary' => $import->summary,
            'error' => $import->error,
            'started_at' => $import->started_at?->toDateTimeString(),
            'completed_at' => $import->completed_at?->toDateTimeString(),
        ];
    }
}
