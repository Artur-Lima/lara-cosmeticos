<?php

namespace App\Models;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'brand_id', 'category_id', 'name', 'slug', 'short_description', 'description',
        'how_to_use', 'composition', 'gender', 'status', 'is_featured', 'seo_title', 'seo_description',
    ];

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
    ];

    protected static function booted(): void
    {
        // Publicar/arquivar produto pode fazer uma categoria aparecer ou sumir do menu.
        $forget = fn () => Cache::forget(Category::MENU_CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'gender' => ProductGender::class,
            'is_featured' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /** Variante exibida por padrão no card: a marcada como default, senão a primeira ativa. */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /** Publicado e com ao menos uma variante ativa (produto sem variante não é vendável). */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereHas('variants', fn (Builder $q) => $q->where('is_active', true));
    }

    /** Tudo que o card da vitrine precisa, em 4 queries fixas para qualquer quantidade de produtos. */
    public function scopeForCard(Builder $query): void
    {
        $query->with([
            'brand:id,name,slug',
            'images' => fn ($q) => $q->limit(1),
            'activeVariants',
        ]);
    }

    /** Menor preço entre as variantes ativas, como coluna "min_price" (para ordenar). */
    public function scopeWithMinPrice(Builder $query): void
    {
        $query->addSelect(['min_price' => ProductVariant::query()
            ->selectRaw('min(price)')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true),
        ]);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function isVisible(): bool
    {
        return $this->status === ProductStatus::Active
            && $this->activeVariants()->exists();
    }

    /** Variante usada no card: a padrão entre as ativas carregadas. */
    public function cardVariant(): ?ProductVariant
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get();

        return $variants->firstWhere('is_default', true) ?? $variants->first();
    }

    public function isSoldOut(): bool
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get();

        return $variants->every(fn (ProductVariant $v) => $v->stock < 1);
    }

    /** "3 tamanhos", "4 cores" ou null quando só há uma opção. */
    public function optionsLabel(): ?string
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get();
        $count = $variants->count();

        if ($count < 2) {
            return null;
        }

        return $variants->contains(fn ($v) => $v->color_hex || $v->shade) ? "{$count} cores" : "{$count} tamanhos";
    }

    /**
     * Faixa de preço das variantes ativas, ex.: [89.90, 189.90].
     * Usa a relação carregada quando disponível (evita N+1 na listagem com ->with('activeVariants')).
     *
     * @return array{0: float, 1: float}|null
     */
    public function priceRange(): ?array
    {
        $prices = ($this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get())
            ->pluck('price')
            ->map(fn ($price) => (float) $price);

        if ($prices->isEmpty()) {
            return null;
        }

        return [$prices->min(), $prices->max()];
    }
}
