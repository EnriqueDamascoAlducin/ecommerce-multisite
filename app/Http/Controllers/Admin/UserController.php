<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create', [
            'availableRoles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        $this->auditLogger->log('user.created', $user, "Usuario {$user->email} creado", [
            'roles' => $validated['roles'] ?? [],
        ]);

        return to_route('admin.users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
            'availableRoles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles($validated['roles'] ?? []);

        $this->auditLogger->log('user.updated', $user, "Usuario {$user->email} actualizado", [
            'roles' => $validated['roles'] ?? [],
        ]);

        return to_route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $email = $user->email;
        $user->delete();

        $this->auditLogger->log('user.deleted', null, "Usuario {$email} eliminado");

        return to_route('admin.users.index')->with('success', 'Usuario eliminado.');
    }
}
