<?php

namespace Database\Factories;

use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImport>
 */
class ProductImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'file_path' => 'imports/products/'.Str::uuid().'.csv',
            'status' => ProductImport::STATUS_PENDING,
            'total_products' => 10,
            'processed_products' => 0,
            'total_images' => 20,
            'processed_images' => 0,
        ];
    }
}
