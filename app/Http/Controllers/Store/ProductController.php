<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): View
    {
        $isPreview = ! $product->isVisible();

        // Rascunho/arquivado: 404 para o público, prévia para o admin.
        abort_if($isPreview && ! $request->user()?->isAdmin(), 404);

        $product->load(['brand:id,name,slug', 'category.parent', 'images', 'activeVariants']);

        $variants = $product->activeVariants;
        $selected = $variants->firstWhere('id', (int) $request->query('variante')) ?? $product->cardVariant();

        $related = $product->category_id
            ? Product::visible()->forCard()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->inRandomOrder()
                ->limit(4)
                ->get()
            : collect();

        return view('store.product', compact('product', 'variants', 'selected', 'related', 'isPreview'));
    }
}
