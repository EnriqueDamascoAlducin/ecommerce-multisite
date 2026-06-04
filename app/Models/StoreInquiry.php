<?php

namespace App\Models;

use Database\Factories\StoreInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInquiry extends Model
{
    /** @use HasFactory<StoreInquiryFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    /** @var list<string> */
    protected $fillable = ['store_id', 'name', 'email', 'interest_area', 'message', 'status'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
