<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreConfiguration extends Model
{
    /** @var list<string> */
    protected $fillable = ['scope', 'scope_id', 'key', 'value'];
}
