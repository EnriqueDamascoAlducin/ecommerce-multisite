<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Mediable extends MorphPivot
{
    protected $table = 'mediables';

    public $incrementing = true;

    /** @var list<string> */
    protected $fillable = ['media_id', 'collection', 'is_primary', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
