<?php

namespace App\Models;

use Database\Factories\DownloadableLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadableLink extends Model
{
    /** @use HasFactory<DownloadableLinkFactory> */
    use HasFactory;

    /** Disco privado donde viven los archivos descargables. */
    public const DISK = 'downloads';

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'title', 'file_path', 'original_name', 'max_downloads', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_downloads' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
