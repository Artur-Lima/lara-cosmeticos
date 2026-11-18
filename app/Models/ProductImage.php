<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'product_variant_id', 'path', 'thumb_path', 'alt', 'position'];

    protected static function booted(): void
    {
        // Apagar o registro apaga também os arquivos (principal e miniatura).
        static::deleted(function (ProductImage $image) {
            Storage::disk('public')->delete(array_filter([$image->path, $image->thumb_path]));
        });
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->path));
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->thumb_path ?? $this->path));
    }
}
