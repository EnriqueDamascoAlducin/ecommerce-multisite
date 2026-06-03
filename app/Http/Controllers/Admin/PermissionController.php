<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Listado de solo lectura de los permisos del sistema, agrupados por módulo.
     */
    public function index(): Response
    {
        $groups = Permission::orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0]);

        return Inertia::render('admin/permissions/index', [
            'permissionGroups' => $groups,
        ]);
    }
}
