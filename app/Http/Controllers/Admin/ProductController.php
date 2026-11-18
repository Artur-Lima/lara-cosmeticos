<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'sem_estoque' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->with(['brand:id,name', 'category:id,name', 'images' => fn ($q) => $q->limit(1), 'activeVariants'])
            ->withCount('variants')
            ->withSum('variants as stock_total', 'stock')
            ->when($filters['q'] ?? null, function ($query, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('barcode', $term)));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['brand'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($filters['sem_estoque'] ?? false, fn ($q) => $q->whereDoesntHave('variants', fn ($v) => $v->where('stock', '>', 0)))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Product(['status' => ProductStatus::Draft]));
    }

    public function store(ProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $product = $save->handle(new Product, $request->validated(), $request->file('images', []));

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Produto cadastrado.');
    }

    public function edit(Product $product): View
    {
        return $this->form($product->load(['variants', 'images']));
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($product, $request->validated(), $request->file('images', []));

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Produto atualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete(); // soft delete: pedidos antigos continuam íntegros

        return redirect()->route('admin.products.index')
            ->with('success', "Produto \"{$product->name}\" excluído.");
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        $image->delete(); // o model apaga os arquivos (principal e miniatura)

        return back()->with('success', 'Foto removida.');
    }

    private function form(Product $product): View
    {
        $variants = $product->exists
            ? $product->variants->map->only([
                'id', 'sku', 'label', 'barcode', 'volume_ml', 'shade', 'color_hex',
                'price', 'compare_at_price', 'stock', 'weight_g', 'is_active',
            ])->values()
            : collect([['id' => null, 'sku' => '', 'label' => '', 'barcode' => '', 'volume_ml' => null, 'shade' => '',
                'color_hex' => null, 'price' => '', 'compare_at_price' => null, 'stock' => 0, 'weight_g' => null, 'is_active' => true]]);

        return view('admin.products.form', [
            'product' => $product,
            'variants' => old('variants', $variants->all()),
            'defaultVariant' => (int) old('default_variant', max(0, $product->variants?->search(fn ($v) => $v->is_default) ?: 0)),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /** Opções "Pai › Filha" para selects, numa query só. */
    private function categoryOptions(): array
    {
        $all = Category::orderBy('position')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $byId = $all->keyBy('id');

        return $all
            ->mapWithKeys(fn ($c) => [$c->id => $c->parent_id ? $byId[$c->parent_id]->name.' › '.$c->name : $c->name])
            ->sort()
            ->all();
    }
}
