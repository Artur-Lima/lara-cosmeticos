<?php

namespace App\Http\Controllers\Store;

use App\Enums\ProductGender;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public const SORTS = [
        'destaques' => 'Destaques',
        'novidades' => 'Novidades',
        'menor-preco' => 'Menor preço',
        'maior-preco' => 'Maior preço',
    ];

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->listing($request, category: $category);
    }

    public function brand(Request $request, Brand $brand): View
    {
        abort_unless($brand->is_active, 404);

        return $this->listing($request, brand: $brand);
    }

    private function listing(Request $request, ?Category $category = null, ?Brand $brand = null): View
    {
        // Filtro inválido na URL vira "sem filtro" em vez de erro 422 para o cliente.
        $validator = validator($request->query(), [
            'busca' => ['nullable', 'string', 'max:100'],
            'publico' => ['nullable', Rule::enum(ProductGender::class)],
            'marca' => ['nullable', 'string', 'max:140'],
            'ordem' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);
        $filters = $validator->valid();
        $sort = $filters['ordem'] ?? 'destaques';

        $brand ??= filled($filters['marca'] ?? null) ? Brand::active()->where('slug', $filters['marca'])->first() : null;

        $products = Product::query()
            ->select('products.*')
            ->visible()
            ->forCard()
            ->withMinPrice()
            ->when($category, function (Builder $q) use ($category) {
                $ids = $category->children()->pluck('id')->push($category->id);
                $q->whereIn('category_id', $ids);
            })
            ->when($brand, fn (Builder $q) => $q->where('brand_id', $brand->id))
            ->when($filters['publico'] ?? null, fn (Builder $q, $g) => $q->whereIn('gender', [$g, ProductGender::Unissex->value]))
            ->when($filters['busca'] ?? null, function (Builder $q, string $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like)));
            })
            ->tap(fn (Builder $q) => match ($sort) {
                'novidades' => $q->latest('products.created_at'),
                'menor-preco' => $q->orderBy('min_price')->orderBy('products.id'),
                'maior-preco' => $q->orderByDesc('min_price')->orderBy('products.id'),
                default => $q->orderByDesc('is_featured')->latest('products.created_at'),
            })
            ->paginate(24)
            ->withQueryString();

        return view('store.catalog', [
            'products' => $products,
            'category' => $category,
            'brand' => $brand,
            'filters' => $filters,
            'sort' => $sort,
            'subcategories' => $category?->children()->active()->orderBy('position')->get(['id', 'name', 'slug', 'parent_id']) ?? collect(),
            'parentCategory' => $category?->parent,
            'brands' => Brand::active()->whereHas('products', fn ($q) => $q->visible())->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
