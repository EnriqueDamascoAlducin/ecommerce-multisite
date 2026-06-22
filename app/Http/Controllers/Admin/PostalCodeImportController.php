<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Customer\PostalCodeCsvImportService;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PostalCodeImportController extends Controller
{
    public function __construct(
        private readonly PostalCodeCsvImportService $imports,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(): Response
    {
        return Inertia::render('admin/postal-codes/import', [
            'result' => session('postal_code_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);

        $path = $data['file']->store('imports/postal-codes', 'local');
        $disk = Storage::disk('local');

        try {
            $result = $this->imports->import($disk->path($path));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $this->auditLogger->log(
            'postal_codes.imported',
            null,
            'Catálogo SEPOMEX importado',
            ['summary' => $result['summary'], 'file_path' => $path],
        );

        return to_route('admin.postal-codes.import.create')
            ->with('postal_code_import_result', $result)
            ->with('success', 'Códigos postales importados.');
    }
}
