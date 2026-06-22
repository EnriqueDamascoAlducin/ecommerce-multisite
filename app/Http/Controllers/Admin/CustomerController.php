<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'website_id' => $request->integer('website_id') ?: null,
            'group_id' => $request->integer('group_id') ?: null,
            'verified' => $request->string('verified')->toString(),
        ];

        $customers = Customer::query()
            ->with(['group', 'website:id,name'])
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn (Builder $q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_FAILED])], 'total')
            ->addSelect(['last_order_at' => Order::select('placed_at')
                ->whereColumn('customer_id', 'customers.id')
                ->latest('placed_at')
                ->limit(1)])
            ->when($filters['search'], fn (Builder $q, string $term) => $q->where(fn (Builder $sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($filters['website_id'], fn (Builder $q, int $id) => $q->where('website_id', $id))
            ->when($filters['group_id'], fn (Builder $q, int $id) => $q->where('group_id', $id))
            ->when($filters['verified'] === 'yes', fn (Builder $q) => $q->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'no', fn (Builder $q) => $q->whereNull('email_verified_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => $this->row($customer));

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => $filters,
            'websites' => $this->websiteOptions(),
            'groups' => $this->groupOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/customers/create', [
            'websites' => $this->websiteOptions(),
            'groups' => $this->groupOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'website_id' => $data['website_id'],
                'group_id' => $data['group_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);

            $this->syncAddresses($customer, $data['addresses'] ?? []);

            return $customer;
        });

        $this->auditLogger->log('customer.created', $customer, "Cliente {$customer->email} creado");

        return to_route('admin.customers.index')->with('success', 'Cliente creado.');
    }

    public function edit(Customer $customer): Response
    {
        $customer->load(['group', 'website:id,name', 'addresses']);

        return Inertia::render('admin/customers/edit', [
            'customer' => [
                'id' => $customer->id,
                'website_id' => $customer->website_id,
                'website' => $customer->website->name,
                'group_id' => $customer->group_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'verified' => (bool) $customer->email_verified_at,
                'created_at' => $customer->created_at?->toDateString(),
                'addresses' => $customer->addresses->map(fn ($address) => $address->only([
                    'id', 'label', 'first_name', 'last_name', 'company', 'phone',
                    'line1', 'line2', 'neighborhood', 'city', 'state', 'postal_code', 'country',
                    'is_default_shipping', 'is_default_billing',
                ]))->values(),
            ],
            'stats' => [
                'orders_count' => $customer->orders()->count(),
                'total_spent' => (string) $customer->orders()->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_FAILED])->sum('total'),
                'last_order_at' => $customer->orders()->latest('placed_at')->value('placed_at')?->toDateString(),
            ],
            'recentOrders' => $customer->orders()
                ->latest('placed_at')
                ->limit(10)
                ->get(['id', 'number', 'total', 'status', 'placed_at'])
                ->map(fn (Order $order) => [
                    'number' => $order->number,
                    'total' => (string) $order->total,
                    'status' => $order->status,
                    'placed_at' => $order->placed_at?->toDateString(),
                    'url' => route('admin.orders.show', $order),
                ]),
            'groups' => $this->groupOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($customer, $data) {
            $customer->fill([
                'group_id' => $data['group_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $customer->password = $data['password'];
            }

            $customer->save();

            $this->syncAddresses($customer, $data['addresses'] ?? []);
        });

        $this->auditLogger->log('customer.updated', $customer, "Cliente {$customer->email} actualizado");

        return to_route('admin.customers.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $email = $customer->email;
        $customer->delete();

        $this->auditLogger->log('customer.deleted', null, "Cliente {$email} eliminado");

        return to_route('admin.customers.index')->with('success', 'Cliente eliminado.');
    }

    /**
     * Reemplaza las direcciones del cliente con las recibidas (upsert por id),
     * garantizando una sola por defecto de envío y de facturación.
     *
     * @param  list<array<string, mixed>>  $addresses
     */
    private function syncAddresses(Customer $customer, array $addresses): void
    {
        $keepIds = [];
        $shippingSet = false;
        $billingSet = false;

        foreach ($addresses as $data) {
            $isShipping = ($data['is_default_shipping'] ?? false) && ! $shippingSet;
            $isBilling = ($data['is_default_billing'] ?? false) && ! $billingSet;
            $shippingSet = $shippingSet || $isShipping;
            $billingSet = $billingSet || $isBilling;

            $payload = collect($data)->only([
                'label', 'first_name', 'last_name', 'company', 'phone',
                'line1', 'line2', 'neighborhood', 'city', 'state', 'postal_code', 'country',
            ])->all();
            $payload['is_default_shipping'] = $isShipping;
            $payload['is_default_billing'] = $isBilling;

            $existing = isset($data['id'])
                ? $customer->addresses()->whereKey($data['id'])->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keepIds[] = $existing->id;
            } else {
                $keepIds[] = $customer->addresses()->create($payload)->id;
            }
        }

        $customer->addresses()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'website' => $customer->website->name,
            'group' => $customer->group ? [
                'name' => $customer->group->name,
                'color' => $customer->group->color,
            ] : null,
            'orders_count' => $customer->orders_count,
            'total_spent' => (string) ($customer->total_spent ?? 0),
            'last_order_at' => $customer->last_order_at ? substr((string) $customer->last_order_at, 0, 10) : null,
            'verified' => (bool) $customer->email_verified_at,
            'created_at' => $customer->created_at?->toDateString(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function websiteOptions(): Collection
    {
        return Website::orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Website $website) => ['id' => $website->id, 'name' => $website->name]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, website_id: int, color: string}>
     */
    private function groupOptions(): Collection
    {
        return CustomerGroup::orderBy('name')->get(['id', 'name', 'website_id', 'color'])
            ->map(fn (CustomerGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'website_id' => $group->website_id,
                'color' => $group->color,
            ]);
    }
}
