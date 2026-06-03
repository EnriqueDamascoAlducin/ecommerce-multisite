<?php

namespace Database\Seeders;

use App\Models\InventorySource;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        InventorySource::firstOrCreate(
            ['code' => 'default'],
            ['name' => 'Almacén principal', 'is_default' => true, 'is_active' => true, 'sort_order' => 1],
        );
    }
}
