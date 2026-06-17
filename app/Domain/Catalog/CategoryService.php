<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * Construye el árbol de categorías de una tienda como estructura anidada
     * lista para enviar a Inertia.
     *
     * @return list<array<string, mixed>>
     */
    public function treeForStore(int $storeId): array
    {
        $categories = Category::query()
            ->where('store_id', $storeId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->buildBranch($categories, null);
    }

    /**
     * Lista plana con indentación por nivel, útil para <select> de "categoría padre".
     *
     * @return list<array{id: int, label: string}>
     */
    public function flattenedForStore(int $storeId, ?int $excludeId = null): array
    {
        $tree = $this->treeForStore($storeId);
        $flat = [];

        $this->walk($tree, 0, function (array $node, int $depth) use (&$flat, $excludeId) {
            if ($node['id'] === $excludeId) {
                return false; // poda la rama: una categoría no puede ser su propio ancestro
            }

            $flat[] = [
                'id' => $node['id'],
                'label' => str_repeat('— ', $depth).$node['name'],
            ];

            return true;
        });

        return $flat;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array<string, mixed>>
     */
    private function buildBranch(Collection $categories, ?int $parentId): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => $category->is_active,
                'children' => $this->buildBranch($categories, $category->id),
            ])
            ->values()
            ->all();
    }

    /**
     * Recorre el árbol en profundidad. Si el callback devuelve false, no desciende.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function walk(array $nodes, int $depth, callable $callback): void
    {
        foreach ($nodes as $node) {
            $descend = $callback($node, $depth);

            if ($descend !== false) {
                $this->walk($node['children'], $depth + 1, $callback);
            }
        }
    }
}
