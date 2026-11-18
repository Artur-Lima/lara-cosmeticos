<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Delivery = 'entrega';
    case Pickup = 'retirada';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Entrega',
            self::Pickup => 'Retirada na loja',
        };
    }
}
