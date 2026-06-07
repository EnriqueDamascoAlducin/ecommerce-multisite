<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerGroupRequest;
use App\Http\Requests\Admin\UpdateCustomerGroupRequest;
use App\Models\CustomerGroup;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $groups = CustomerGroup::query()
            ->with('website:id,name')
            ->withCount('customers')
            ->orderBy('website_id')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'color' => $group->color,
                'description' => $group->description,
                'is_default' => $group->is_default,
                'website' => $group->website->name,
                'customers_count' => $group->customers_count,
            ]);

        return Inertia::render('admin/customer-groups/index', [
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/customer-groups/create', $this->formData());
    }

    public function store(StoreCustomerGroupRequest $request): RedirectResponse
    {
        $group = CustomerGroup::create($request->validated());
        $this->applyDefault($group);

        $this->auditLogger->log('customer_group.created', $group, "Grupo de clientes «{$group->name}» creado");

        return to_route('admin.customer-groups.index')->with('success', 'Grupo creado.');
    }

    public function edit(CustomerGroup $customerGroup): Response
    {
        return Inertia::render('admin/customer-groups/edit', [
            'group' => $customerGroup->only(['id', 'website_id', 'name', 'code', 'description', 'color', 'is_default']),
            ...$this->formData(),
        ]);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->update($request->validated());
        $this->applyDefault($customerGroup);

        $this->auditLogger->log('customer_group.updated', $customerGroup, "Grupo de clientes «{$customerGroup->name}» actualizado");

        return to_route('admin.customer-groups.index')->with('success', 'Grupo actualizado.');
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        $name = $customerGroup->name;
        $customerGroup->delete();

        $this->auditLogger->log('customer_group.deleted', null, "Grupo de clientes «{$name}» eliminado");

        return to_route('admin.customer-groups.index')->with('success', 'Grupo eliminado.');
    }

    /**
     * Garantiza un solo grupo por defecto por website.
     */
    private function applyDefault(CustomerGroup $group): void
    {
        if ($group->is_default) {
            CustomerGroup::where('website_id', $group->website_id)
                ->whereKeyNot($group->id)
                ->update(['is_default' => false]);
        }
    }

    /**
     * @return array{websites: Collection<int, array{id: int, name: string}>}
     */
    private function formData(): array
    {
        return [
            'websites' => Website::orderBy('sort_order')->get(['id', 'name'])
                ->map(fn (Website $website) => ['id' => $website->id, 'name' => $website->name]),
        ];
    }
}
