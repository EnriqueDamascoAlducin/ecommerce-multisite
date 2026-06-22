<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\StockCsvImportService;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StockImportController extends Controller
{
    public function __construct(
        private readonly StockCsvImportService $imports,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(): Response
    {
        return Inertia::render('admin/inventory/import', [
            'result' => session('stock_import_result'),
            'token' => session('stock_import_token'),
        ]);
    }

    public function validateUpload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $token = (string) Str::uuid();
        $path = $data['file']->storeAs('imports/stock', "{$token}.csv", 'local');
        $result = $this->imports->preview(Storage::disk('local')->path($path));

        return to_route('admin.inventory.import.create')
            ->with('stock_import_result', $result)
            ->with('stock_import_token', $token);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $path = "imports/stock/{$data['token']}.csv";
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $result = $this->imports->apply($disk->path($path), $request->user());

        $this->auditLogger->log(
            'inventory.stock_imported',
            null,
            'Importacion CSV de stock aplicada',
            ['summary' => $result['summary'], 'file_path' => $path],
        );

        return to_route('admin.inventory.import.create')
            ->with('stock_import_result', $result)
            ->with('success', 'Stock importado.');
    }
}
