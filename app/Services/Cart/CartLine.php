<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;

final readonly class CartLine
{
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
    ) {}

    public function product(): Product
    {
        return $this->variant->product;
    }

    public function unitPrice(): float
    {
        return (float) $this->variant->price;
    }

    public function total(): float
    {
        return round($this->unitPrice() * $this->quantity, 2);
    }
}
