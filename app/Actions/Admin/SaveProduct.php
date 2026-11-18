<?php

namespace App\Actions\Admin;

use App\Models\Product;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cria/atualiza um produto com suas variantes e fotos numa única transação.
 * Variantes que sumirem do formulário são excluídas (soft delete), preservando
 * o vínculo com pedidos antigos.
 */
class SaveProduct
{
    public function __construct(private readonly ImageOptimizer $images) {}

    private const PRODUCT_FIELDS = [
        'name', 'slug', 'brand_id', 'category_id', 'short_description', 'description',
        'how_to_use', 'composition', 'gender', 'status', 'is_featured', 'seo_title', 'seo_description',
    ];

    private const VARIANT_FIELDS = [
        'sku', 'label', 'barcode', 'volume_ml', 'shade', 'color_hex', 'price',
        'compare_at_price', 'stock', 'weight_g', 'is_active',
    ];

    /**
     * @param  array<string, mixed>  $data  dados validados por ProductRequest
     * @param  array<int, UploadedFile>  $images
     */
    public function handle(Product $product, array $data, array $images = []): Product
    {
        $stored = [];

        try {
            DB::transaction(function () use ($product, $data, $images, &$stored) {
                $product->fill(Arr::only($data, self::PRODUCT_FIELDS))->save();

                $this->syncVariants($product, $data['variants'], (int) $data['default_variant']);

                $position = (int) $product->images()->max('position');

                foreach ($images as $file) {
                    $files = $this->images->storeProductImage($file, "products/{$product->id}");
                    array_push($stored, $files['path'], $files['thumb_path']);

                    $product->images()->create($files + [
                        'alt' => $product->name,
                        'position' => ++$position,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Se a transação falhar, não deixa arquivo órfão no disco.
            Storage::disk('public')->delete($stored);

            throw $e;
        }

        return $product->refresh();
    }

    private function syncVariants(Product $product, array $variants, int $defaultIndex): void
    {
        $keptIds = collect($variants)->pluck('id')->filter()->all();

        // Variantes removidas do formulário: soft delete. O SKU delas continua
        // reservado (índice unique), de propósito, para rastrear pedidos antigos.
        $product->variants()->whereNotIn('id', $keptIds)->get()->each->delete();

        foreach (array_values($variants) as $position => $row) {
            $attributes = Arr::only($row, self::VARIANT_FIELDS) + [
                'position' => $position,
                'is_default' => $position === $defaultIndex,
            ];

            if (! empty($row['id'])) {
                $product->variants()->whereKey($row['id'])->firstOrFail()->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }
    }
}
