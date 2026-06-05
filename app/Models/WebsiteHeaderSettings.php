<?php

namespace App\Models;

use Database\Factories\WebsiteHeaderSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteHeaderSettings extends Model
{
    /** @use HasFactory<WebsiteHeaderSettingsFactory> */
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_SOCIAL = 'social';

    public const TYPE_IMAGE = 'image';

    /** @var list<string> */
    public const BLOCK_TYPES = [self::TYPE_TEXT, self::TYPE_SOCIAL, self::TYPE_IMAGE];

    /**
     * Plataformas sociales soportadas (con icono disponible en lucide-react).
     *
     * @var list<string>
     */
    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'];

    /** @var list<string> */
    protected $fillable = [
        'website_id',
        'cintillo_enabled',
        'cintillo_blocks',
        'cintillo_show_on_mobile',
        'cintillo_text_color',
        'cintillo_background_color',
        'header_text_color',
        'header_background_color',
        'menu_text_color',
        'menu_background_color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cintillo_enabled' => 'boolean',
            'cintillo_show_on_mobile' => 'boolean',
            'cintillo_blocks' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
