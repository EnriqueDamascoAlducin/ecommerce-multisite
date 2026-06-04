<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permisos iniciales del admin, agrupados por módulo.
     *
     * @var array<string, list<string>>
     */
    private array $permissionGroups = [
        'admin.users' => ['view', 'create', 'edit', 'delete'],
        'admin.roles' => ['view', 'create', 'edit', 'delete'],
        'catalog.products' => ['view', 'create', 'edit', 'delete'],
        'catalog.categories' => ['view', 'create', 'edit', 'delete'],
        'catalog.attributes' => ['view', 'create', 'edit', 'delete'],
        'media' => ['view', 'upload', 'delete'],
        'inventory' => ['view', 'adjust'],
        'sales.orders' => ['view', 'edit', 'cancel'],
        'sales.invoices' => ['view', 'create', 'cancel'],
        'sales.shipments' => ['view', 'create', 'edit', 'cancel'],
        'reports' => ['view'],
        'settings' => ['payments', 'shipping', 'stores'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->createPermissions();

        $rolePermissions = [
            'Super Admin' => $permissions,
            'Administrador' => $this->reject($permissions, fn (string $p) => str_starts_with($p, 'admin.roles.')),
            'Catálogo' => $this->filter($permissions, fn (string $p) => str_starts_with($p, 'catalog.') || str_starts_with($p, 'media.') || $p === 'inventory.view'),
            'Inventario' => $this->filter($permissions, fn (string $p) => str_starts_with($p, 'inventory.')),
            'Ventas' => $this->filter($permissions, fn (string $p) => str_starts_with($p, 'sales.') || $p === 'reports.view'),
            'Marketing' => ['catalog.products.view', 'media.view', 'media.upload'],
            'Soporte' => ['sales.orders.view', 'admin.users.view', 'reports.view'],
            'Solo lectura' => $this->filter($permissions, fn (string $p) => str_ends_with($p, '.view')),
        ];

        foreach ($rolePermissions as $roleName => $grantedPermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($grantedPermissions);
        }

        $this->createSuperAdminUser();
    }

    /**
     * @return list<string>
     */
    private function createPermissions(): array
    {
        $names = [];

        foreach ($this->permissionGroups as $group => $actions) {
            foreach ($actions as $action) {
                $name = "{$group}.{$action}";
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function filter(array $permissions, callable $callback): array
    {
        return array_values(array_filter($permissions, $callback));
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function reject(array $permissions, callable $callback): array
    {
        return array_values(array_filter($permissions, fn (string $p) => ! $callback($p)));
    }

    private function createSuperAdminUser(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')],
        );

        $user->assignRole('Super Admin');
    }
}
