<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::roots()
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Category(['is_active' => true, 'position' => 0]));
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('success', 'Categoria cadastrada.');
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('admin.categories.index')->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Categoria com produtos ou subcategorias não pode ser excluída. Desative-a.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Categoria excluída.');
    }

    private function form(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            // Só 2 níveis: o pai precisa ser raiz e não pode ser a própria categoria.
            'parents' => Category::roots()->whereKeyNot($category->id)->orderBy('position')->pluck('name', 'id'),
        ]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // Uma categoria que já tem filhas não pode virar filha (manteria 3 níveis).
        if ($category && filled($data['parent_id'] ?? null) && $category->children()->exists()) {
            abort(back()->withInput()->withErrors(['parent_id' => 'Esta categoria tem subcategorias e não pode ficar dentro de outra.']));
        }

        return $data + ['position' => (int) ($data['position'] ?? 0), 'is_active' => $request->boolean('is_active')];
    }
}
