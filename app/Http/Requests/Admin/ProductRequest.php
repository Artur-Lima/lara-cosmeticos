<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->values()
            ->map(function (array $v) {
                $v['sku'] = isset($v['sku']) ? strtoupper(trim($v['sku'])) : null;
                $v['color_hex'] = filled($v['color_hex'] ?? null) ? strtolower($v['color_hex']) : null;
                $v['is_active'] = filter_var($v['is_active'] ?? false, FILTER_VALIDATE_BOOL);

                // Campos numéricos vazios chegam como "" do formulário.
                foreach (['id', 'volume_ml', 'compare_at_price', 'weight_g'] as $field) {
                    if (($v[$field] ?? null) === '') {
                        $v[$field] = null;
                    }
                }

                return $v;
            })
            ->all();

        $this->merge([
            'slug' => filled($this->input('slug')) ? str($this->input('slug'))->slug()->value() : null,
            'is_featured' => $this->boolean('is_featured'),
            'variants' => $variants,
        ]);
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('products', 'slug')->ignore($product?->id)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'how_to_use' => ['nullable', 'string', 'max:5000'],
            'composition' => ['nullable', 'string', 'max:5000'],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],

            'default_variant' => ['required', 'integer', 'min:0'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            // id só pode ser de variante deste produto (impede editar variante alheia).
            'variants.*.id' => [
                'nullable', 'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $product?->id ?? 0)->whereNull('deleted_at'),
            ],
            'variants.*.sku' => ['required', 'string', 'max:60', 'distinct', 'regex:/^[A-Z0-9._-]+$/'],
            'variants.*.label' => ['required', 'string', 'max:80'],
            'variants.*.barcode' => ['nullable', 'string', 'max:20'],
            'variants.*.volume_ml' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'variants.*.shade' => ['nullable', 'string', 'max:60'],
            'variants.*.color_hex' => ['nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'variants.*.price' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.weight_g' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.is_active' => ['boolean'],

            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $variants = $this->input('variants', []);

                foreach ($variants as $i => $v) {
                    // SKU é único na tabela inteira (inclusive variantes excluídas, como o índice do banco).
                    $taken = DB::table('product_variants')
                        ->where('sku', $v['sku'] ?? '')
                        ->when($v['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
                        ->exists();

                    if ($taken) {
                        $validator->errors()->add("variants.$i.sku", 'Este SKU já está em uso em outro produto.');
                    }

                    if (isset($v['compare_at_price'], $v['price']) && (float) $v['compare_at_price'] <= (float) $v['price']) {
                        $validator->errors()->add("variants.$i.compare_at_price", 'O preço "de" precisa ser maior que o preço de venda.');
                    }
                }

                if (! array_key_exists((int) $this->input('default_variant'), $variants)) {
                    $validator->errors()->add('default_variant', 'Escolha qual variante aparece por padrão.');
                }
            },
        ];
    }
}
