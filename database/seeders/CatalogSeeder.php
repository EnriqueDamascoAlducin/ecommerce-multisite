<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Website;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedAttributes();
    }

    private function seedCategories(): void
    {
        $interferenciales = Website::where('code', 'interferenciales')->first();
        $veterinaria = Website::where('code', 'veterinaria')->first();

        if ($interferenciales) {
            $electronica = $this->category($interferenciales, 'Electrónica');
            $this->category($interferenciales, 'Celulares', $electronica);
            $this->category($interferenciales, 'Audio', $electronica);

            $deportes = $this->category($interferenciales, 'Deportes');
            $this->category($interferenciales, 'Fútbol', $deportes);
            $this->category($interferenciales, 'Running', $deportes);
        }

        if ($veterinaria) {
            $alimentos = $this->category($veterinaria, 'Alimentos');
            $this->category($veterinaria, 'Perros', $alimentos);
            $this->category($veterinaria, 'Gatos', $alimentos);
            $this->category($veterinaria, 'Accesorios');
        }
    }

    private function category(Website $website, string $name, ?Category $parent = null): Category
    {
        return Category::firstOrCreate(
            ['website_id' => $website->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'parent_id' => $parent?->id, 'is_active' => true],
        );
    }

    private function seedAttributes(): void
    {
        $color = Attribute::firstOrCreate(
            ['code' => 'color'],
            ['name' => 'Color', 'type' => Attribute::TYPE_SELECT, 'is_filterable' => true, 'is_configurable' => true],
        );
        $this->options($color, ['Rojo', 'Azul', 'Verde', 'Negro', 'Blanco']);

        $talla = Attribute::firstOrCreate(
            ['code' => 'talla'],
            ['name' => 'Talla', 'type' => Attribute::TYPE_SELECT, 'is_filterable' => true, 'is_configurable' => true],
        );
        $this->options($talla, ['CH', 'M', 'G', 'XG']);

        Attribute::firstOrCreate(
            ['code' => 'material'],
            ['name' => 'Material', 'type' => Attribute::TYPE_TEXT, 'is_filterable' => false],
        );

        Attribute::firstOrCreate(
            ['code' => 'garantia_meses'],
            ['name' => 'Garantía (meses)', 'type' => Attribute::TYPE_NUMBER],
        );
    }

    /**
     * @param  list<string>  $labels
     */
    private function options(Attribute $attribute, array $labels): void
    {
        foreach (array_values($labels) as $index => $label) {
            $attribute->options()->firstOrCreate(
                ['value' => Str::slug($label, '_')],
                ['label' => $label, 'sort_order' => $index],
            );
        }
    }
}
