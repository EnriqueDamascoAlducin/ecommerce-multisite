<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomerDownloadGrant;
use App\Models\DownloadableLink;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * Lista las descargas disponibles del cliente autenticado.
     */
    public function index(): Response
    {
        $customer = auth('customer')->user();

        $downloads = $customer->downloadGrants()
            ->with('order:id,number')
            ->latest('granted_at')
            ->get()
            ->map(fn (CustomerDownloadGrant $grant) => [
                'id' => $grant->id,
                'title' => $grant->title,
                'order_number' => $grant->order?->number,
                'downloads_used' => $grant->downloads_used,
                'max_downloads' => $grant->max_downloads,
                'remaining' => $grant->remaining(),
                'available' => $grant->hasRemaining(),
                'granted_at' => $grant->granted_at?->toDateString(),
            ]);

        return Inertia::render('storefront/account/downloads', [
            'downloads' => $downloads,
        ]);
    }

    /**
     * Entrega el archivo de un grant del cliente, descontando un uso.
     */
    public function download(CustomerDownloadGrant $grant): StreamedResponse
    {
        $customer = auth('customer')->user();

        abort_unless($grant->customer_id === $customer->id, 403);
        abort_unless($grant->hasRemaining(), 403, 'Sin descargas disponibles.');

        $link = $grant->link;

        abort_if($link === null, 404);

        $grant->increment('downloads_used');

        return Storage::disk(DownloadableLink::DISK)->download(
            $link->file_path,
            $link->original_name ?? $link->title,
        );
    }
}
