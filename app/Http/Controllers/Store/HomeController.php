<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featured = Product::visible()->featured()->forCard()->latest()->limit(8)->get();

        $newest = Product::visible()->forCard()
            ->whereNotIn('id', $featured->modelKeys())
            ->latest()
            ->limit(8)
            ->get();

        // Categorias principais com contagem de produtos visíveis (inclui subcategorias).
        $categories = Category::active()->roots()->orderBy('position')->with('children:id,parent_id')->get()
            ->map(function (Category $category) {
                $ids = $category->children->pluck('id')->push($category->id);
                $category->visible_count = Product::visible()->whereIn('category_id', $ids)->count();

                return $category;
            })
            ->where('visible_count', '>', 0)
            ->values();

        return view('store.home', compact('featured', 'newest', 'categories'));
    }
}
