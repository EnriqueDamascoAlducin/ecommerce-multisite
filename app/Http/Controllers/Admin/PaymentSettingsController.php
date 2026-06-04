<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Payment\PaymentSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentSettingsRequest;
use App\Models\PaymentGatewaySetting;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly PaymentSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        $gateways = collect($this->registry->all())->map(fn (PaymentGateway $gateway) => [
            'code' => $gateway->code(),
            'label' => $gateway->label(),
            'fields' => $gateway->configFields(),
            'supports_mode' => $gateway->supportsMode(),
        ])->values();

        $fieldsByGateway = $gateways->keyBy('code');

        $settings = PaymentGatewaySetting::all()->map(function (PaymentGatewaySetting $setting) use ($fieldsByGateway) {
            $fields = $fieldsByGateway[$setting->gateway]['fields'] ?? [];
            $values = [];
            $secretSet = [];

            foreach ($fields as $field) {
                $value = $setting->credential($field['key']);

                if ($field['secret']) {
                    // No se exponen secretos al cliente; solo si ya tienen valor.
                    $values[$field['key']] = '';
                    $secretSet[$field['key']] = ! empty($value);
                } else {
                    $values[$field['key']] = (string) ($value ?? '');
                }
            }

            return [
                'website_id' => $setting->website_id,
                'gateway' => $setting->gateway,
                'is_enabled' => $setting->is_enabled,
                'mode' => $setting->mode,
                'values' => $values,
                'secret_set' => $secretSet,
            ];
        })->values();

        return Inertia::render('admin/payments/index', [
            'websites' => Website::orderBy('name')->get(['id', 'name']),
            'gateways' => $gateways,
            'settings' => $settings,
        ]);
    }

    public function update(PaymentSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $gateway = collect($this->registry->all())->firstWhere(fn (PaymentGateway $g) => $g->code() === $data['gateway']);

        abort_if($gateway === null, 422, 'Pasarela desconocida.');

        $fields = collect($gateway->configFields())->keyBy('key');

        $setting = PaymentGatewaySetting::firstOrNew([
            'website_id' => $data['website_id'],
            'gateway' => $data['gateway'],
        ]);

        $credentials = $setting->credentials ?? [];

        foreach (($data['credentials'] ?? []) as $key => $value) {
            $field = $fields->get($key);

            if (! $field) {
                continue;
            }

            // Un secreto en blanco conserva el valor guardado (no se borra al editar).
            if ($field['secret'] && ($value === null || $value === '')) {
                continue;
            }

            $credentials[$key] = $value;
        }

        $setting->fill([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'mode' => $data['mode'] ?? PaymentGatewaySetting::MODE_SANDBOX,
            'credentials' => $credentials,
        ])->save();

        $this->settings->flush();

        $this->auditLogger->log(
            'payment_settings.updated',
            $setting,
            "Configuración de pago «{$data['gateway']}» actualizada para el sitio #{$data['website_id']}",
        );

        return back()->with('success', 'Configuración de pago guardada.');
    }
}
