<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Dados fictícios para desenvolvimento local. Nunca roda em produção
 * (ver DatabaseSeeder). Nomes de produto gerados pelo Faker.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brands = Brand::all();
        $perfumes = Category::whereIn('slug', ['perfumes-femininos', 'perfumes-masculinos'])->get();
        $boca = Category::where('slug', 'boca')->first();

        // Perfumes com volumes
        foreach (range(1, 8) as $i) {
            Product::factory()
                ->withVolumes()
                ->featured()
                ->create([
                    'brand_id' => $brands->random()->id,
                    'category_id' => $perfumes->random()->id,
                ]);
        }

        // Batom com tons
        $batom = Product::factory()->create([
            'name' => 'Batom Matte Demo',
            'brand_id' => $brands->random()->id,
            'category_id' => $boca?->id,
            'gender' => null,
        ]);

        foreach ([['Nude 01', '#c8a091'], ['Rosé 02', '#b5656b'], ['Vermelho 03', '#a1121f']] as $i => [$tom, $hex]) {
            ProductVariant::factory()->for($batom)->shade($tom, $hex)->create([
                'price' => 39.90,
                'is_default' => $i === 0,
                'position' => $i,
            ]);
        }

        // Um produto em promoção e um esgotado, para testar a vitrine
        $vitrine = fn () => Product::factory()->create([
            'brand_id' => $brands->random()->id,
            'category_id' => $perfumes->random()->id,
        ]);

        ProductVariant::factory()->for($vitrine())->onSale(189.90, 149.90)->create(['is_default' => true]);
        ProductVariant::factory()->for($vitrine())->outOfStock()->create(['is_default' => true]);

        ShippingZone::firstOrCreate(['name' => 'Centro'], ['fee' => 5.00, 'free_above' => 150.00, 'estimate' => 'até 2h']);
        ShippingZone::firstOrCreate(['name' => 'Demais bairros'], ['fee' => 8.00, 'free_above' => 200.00, 'estimate' => 'no mesmo dia']);
    }
}
