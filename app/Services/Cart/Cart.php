<?php

namespace App\Services\Cart;

use App\Enums\ProductStatus;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Carrinho em sessão. Guarda só [variant_id => quantidade]; preço, nome e
 * estoque são sempre lidos do banco, então mudança de preço ou estoque feita
 * pela loja vale na hora para quem já está com o item no carrinho.
 */
class Cart
{
    private const KEY = 'cart.items';

    /** @var list<string> */
    private array $notices = [];

    private ?Collection $lines = null;

    public function __construct(private readonly Session $session) {}

    /** Total de unidades, só da sessão (barato para o cabeçalho). */
    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /** @return int quantidade final da variante no carrinho */
    public function add(ProductVariant $variant, int $quantity = 1): int
    {
        $this->assertPurchasable($variant);

        $items = $this->items();
        $wanted = ($items[$variant->id] ?? 0) + max(1, $quantity);
        $final = min($wanted, $this->limitFor($variant));

        $items[$variant->id] = $final;
        $this->save($items);

        if ($final < $wanted) {
            $this->notices[] = "Só temos {$final} ".($final === 1 ? 'unidade' : 'unidades')." de {$this->describe($variant)}.";
        }

        return $final;
    }

    public function update(ProductVariant $variant, int $quantity): int
    {
        if ($quantity < 1) {
            $this->remove($variant->id);

            return 0;
        }

        $items = $this->items();

        if (! isset($items[$variant->id])) {
            return $this->add($variant, $quantity);
        }

        $this->assertPurchasable($variant);
        $final = min($quantity, $this->limitFor($variant));

        $items[$variant->id] = $final;
        $this->save($items);

        if ($final < $quantity) {
            $this->notices[] = "Só temos {$final} ".($final === 1 ? 'unidade' : 'unidades')." de {$this->describe($variant)}.";
        }

        return $final;
    }

    public function remove(int $variantId): void
    {
        $items = $this->items();
        unset($items[$variantId]);
        $this->save($items);
    }

    public function clear(): void
    {
        $this->save([]);
    }

    /**
     * Linhas válidas, já reconciliadas com o banco: item que saiu de linha é
     * removido e quantidade acima do estoque é reduzida, com aviso ao cliente.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $items = $this->items();

        if ($items === []) {
            return $this->lines = collect();
        }

        $variants = ProductVariant::query()
            ->with(['product.images' => fn ($q) => $q->limit(1), 'product.brand:id,name'])
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $lines = collect();
        $changed = false;

        foreach ($items as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if (! $variant || ! $this->isPurchasable($variant)) {
                $name = $variant ? $this->describe($variant) : 'Um produto';
                $this->notices[] = "{$name} não está mais disponível e saiu do carrinho.";
                unset($items[$variantId]);
                $changed = true;

                continue;
            }

            $limit = $this->limitFor($variant);

            if ($quantity > $limit) {
                $this->notices[] = "Ajustamos {$this->describe($variant)} para {$limit} ".($limit === 1 ? 'unidade' : 'unidades').', o que temos em estoque.';
                $quantity = $items[$variantId] = $limit;
                $changed = true;
            }

            $lines->push(new CartLine($variant, $quantity));
        }

        if ($changed) {
            $this->save($items);
        }

        return $this->lines = $lines;
    }

    public function subtotal(): float
    {
        return round($this->lines()->sum(fn (CartLine $line) => $line->total()), 2);
    }

    /** @return list<string> */
    public function notices(): array
    {
        return $this->notices;
    }

    /** @return array<int, int> */
    private function items(): array
    {
        return array_map('intval', $this->session->get(self::KEY, []));
    }

    private function save(array $items): void
    {
        $this->session->put(self::KEY, array_filter($items, fn ($q) => $q > 0));
        $this->lines = null;
    }

    private function isPurchasable(ProductVariant $variant): bool
    {
        return $variant->is_active
            && ! $variant->trashed()
            && $variant->stock > 0
            && $variant->product
            && ! $variant->product->trashed()
            && $variant->product->status === ProductStatus::Active;
    }

    private function assertPurchasable(ProductVariant $variant): void
    {
        $variant->loadMissing('product');

        if (! $this->isPurchasable($variant)) {
            throw new CartException(
                $variant->is_active && $variant->stock < 1
                    ? "{$this->describe($variant)} está esgotado."
                    : 'Este produto não está disponível para compra.'
            );
        }
    }

    private function limitFor(ProductVariant $variant): int
    {
        return max(0, min($variant->stock, (int) config('shop.max_qty_per_item', 10)));
    }

    private function describe(ProductVariant $variant): string
    {
        $product = $variant->product?->name ?? 'Produto';

        return $variant->product?->variants()->count() > 1 ? "{$product} ({$variant->label})" : $product;
    }
}
