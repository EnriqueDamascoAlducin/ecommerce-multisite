<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DownloadableLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadableController extends Controller
{
    /**
     * Sube un archivo descargable al disco privado y devuelve su ruta para que
     * el formulario de producto la guarde como enlace (no persiste nada aún).
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50 MB
        ]);

        $file = $request->file('file');
        $path = $file->store('files', DownloadableLink::DISK);

        return response()->json([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }
}
