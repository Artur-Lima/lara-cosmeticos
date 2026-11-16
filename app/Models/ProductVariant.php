<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'label', 'volume_ml', 'shade', 'color_hex',
        'price', 'compare_at_price', 'stock', 'weight_g', 'is_default', 'is_active', 'position',
    ];

    protected $attributes = [
        'stock' => 0,
        'is_default' => false,
        'is_active' => true,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock' => 'integer',
            'volume_ml' => 'integer',
            'weight_g' => 'integer',
            'position' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }

    public function inStock(int $quantity = 1): bool
    {
        return $this->is_active && $this->stock >= $quantity;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->price;
    }

    /** Percentual de desconto inteiro sobre o preço "de" (ex.: 21 para 21% OFF). */
    public function discountPercent(): ?int
    {
        if (! $this->isOnSale()) {
            return null;
        }

        return (int) round((1 - (float) $this->price / (float) $this->compare_at_price) * 100);
    }

    /**
     * Baixa atômica de estoque: um único UPDATE condicionado a stock >= quantidade.
     * Duas confirmações de pagamento simultâneas pelo último item não deixam o
     * estoque negativo: só uma consegue; a outra recebe false e o pedido deve
     * ser sinalizado para a loja (estorno ou troca).
     */
    public function decrementStock(int $quantity): bool
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $affected = static::query()
            ->whereKey($this->getKey())
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($affected === 1) {
            $this->refresh();

            return true;
        }

        return false;
    }

    /** Devolve estoque (cancelamento/estorno de pedido já pago). */
    public function incrementStock(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        static::query()->whereKey($this->getKey())->increment('stock', $quantity);
        $this->refresh();
    }
}
