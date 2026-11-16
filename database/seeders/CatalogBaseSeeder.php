<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Marcas e árvore de categorias iniciais. Idempotente (pode rodar em produção
 * mais de uma vez). Taxas de entrega NÃO entram aqui: são cadastradas pela
 * loja no admin, porque dependem do preço real que ela cobra.
 */
class CatalogBaseSeeder extends Seeder
{
    private const BRANDS = ['Natura', 'O Boticário', 'Eudora'];

    private const CATEGORIES = [
        'Perfumaria' => ['Perfumes Femininos', 'Perfumes Masculinos', 'Body Splash'],
        'Maquiagem' => ['Rosto', 'Olhos', 'Boca'],
        'Corpo e Banho' => ['Hidratantes', 'Sabonetes', 'Desodorantes'],
        'Cabelos' => ['Shampoo e Condicionador', 'Tratamento'],
        'Skincare' => [],
        'Kits e Presentes' => [],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $name) {
            Brand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }

        $position = 0;

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'position' => $position++],
            );

            foreach ($children as $i => $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'position' => $i],
                );
            }
        }
    }
}
