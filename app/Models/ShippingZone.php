<?php

namespace App\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    /** Entrega própria só nesta cidade (ver docs/decisoes.md, ADR-007). */
    public const CITY = 'Itapuã do Oeste';

    public const STATE = 'RO';

    protected $fillable = ['name', 'fee', 'free_above', 'estimate', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'free_above' => 'decimal:2',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('name');
    }

    /** Taxa para um subtotal: zera quando atinge o valor de frete grátis da zona. */
    public function feeFor(float $subtotal): float
    {
        if ($this->free_above !== null && $subtotal >= (float) $this->free_above) {
            return 0.0;
        }

        return (float) $this->fee;
    }
}
