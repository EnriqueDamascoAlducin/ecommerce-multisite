<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    /** @use HasFactory<\Database\Factories\AttributeFactory> */
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_NUMBER = 'number';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DATE = 'date';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_NUMBER,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
        self::TYPE_BOOLEAN,
        self::TYPE_DATE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'code', 'name', 'type', 'is_required', 'is_filterable',
        'is_visible', 'is_configurable', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_configurable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<AttributeOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * Los tipos select y multiselect usan opciones predefinidas.
     */
    public function hasOptions(): bool
    {
        return in_array($this->type, [self::TYPE_SELECT, self::TYPE_MULTISELECT], true);
    }
}
