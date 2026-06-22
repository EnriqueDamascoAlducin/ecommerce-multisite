<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCodeSettlement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'postal_code',
        'settlement',
        'settlement_type',
        'municipality',
        'state',
        'city',
        'zone',
    ];
}
