<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Store\AdminScopeManager;
use App\Domain\Store\ScopedConfigService;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreConfigurationController extends Controller
{
    /**
     * Claves de configuración editables y su etiqueta.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'currency' => 'Moneda',
        'locale' => 'Idioma',
        'base_url' => 'URL base',
        'logo' => 'Logo (ruta/URL)',
        'sender_email' => 'Email remitente',
    ];

    public function __construct(
        private readonly AdminScopeManager $scope,
        private readonly ScopedConfigService $config,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $current = $this->scope->current();
        $website = $this->scope->website();
        $store = $this->scope->store();
        $own = $this->config->allForScope($current['type'], $current['id']);

        // Para el valor heredado mostramos el efectivo del scope padre (sin el override actual).
        $parentWebsite = $current['type'] === ScopedConfigService::SCOPE_STORE ? $website : null;

        $fields = collect(self::FIELDS)->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'value' => $own[$key] ?? '',
            'inherited' => $current['type'] === ScopedConfigService::SCOPE_GLOBAL
                ? null
                : $this->config->get($key, null, $parentWebsite),
        ])->values();

        return Inertia::render('admin/configuration/index', [
            'scope' => [
                'type' => $current['type'],
                'id' => $current['id'],
                'label' => $this->scope->currentLabel($request->user()),
            ],
            'options' => $this->scope->options($request->user()),
            'fields' => $fields,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'values' => ['array'],
            'values.*' => ['nullable', 'string', 'max:255'],
        ]);

        $current = $this->scope->current();

        foreach (array_keys(self::FIELDS) as $key) {
            $value = $validated['values'][$key] ?? null;
            $this->config->set($current['type'], $current['id'], $key, $value !== '' ? $value : null);
        }

        $this->auditLogger->log('configuration.updated', null, "Configuración actualizada ({$current['type']}:{$current['id']})", [
            'scope' => $current,
        ]);

        return back()->with('success', 'Configuración guardada.');
    }
}
