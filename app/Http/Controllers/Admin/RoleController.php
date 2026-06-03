<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Roles que no pueden eliminarse para evitar bloqueos del sistema.
     *
     * @var list<string>
     */
    private const PROTECTED_ROLES = ['Super Admin'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'protected' => in_array($role->name, self::PROTECTED_ROLES, true),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/create', [
            'availablePermissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->auditLogger->log('role.created', $role, "Rol {$role->name} creado", [
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return to_route('admin.roles.index')->with('success', 'Rol creado.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'protected' => in_array($role->name, self::PROTECTED_ROLES, true),
            ],
            'availablePermissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->auditLogger->log('role.updated', $role, "Rol {$role->name} actualizado", [
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return to_route('admin.roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->with('error', 'Este rol no se puede eliminar.');
        }

        $name = $role->name;
        $role->delete();

        $this->auditLogger->log('role.deleted', null, "Rol {$name} eliminado");

        return to_route('admin.roles.index')->with('success', 'Rol eliminado.');
    }
}
