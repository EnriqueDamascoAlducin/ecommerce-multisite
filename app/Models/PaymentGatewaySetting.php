<?php

namespace App\Models;

use Database\Factories\PaymentGatewaySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewaySetting extends Model
{
    /** @use HasFactory<PaymentGatewaySettingFactory> */
    use HasFactory;

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_LIVE = 'live';

    /** @var list<string> */
    public const MODES = [self::MODE_SANDBOX, self::MODE_LIVE];

    /** @var list<string> */
    protected $fillable = [
        'website_id', 'gateway', 'is_enabled', 'mode', 'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            // Las credenciales se cifran en reposo (clave APP_KEY).
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Valor de una credencial concreta (o null si no está definida).
     */
    public function credential(string $key): mixed
    {
        return ($this->credentials ?? [])[$key] ?? null;
    }

    public function isLive(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }
}
