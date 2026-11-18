<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'aguardando_pagamento';
    case Paid = 'pago';
    case Preparing = 'em_separacao';
    case ReadyForPickup = 'pronto_retirada';
    case OutForDelivery = 'saiu_entrega';
    case Delivered = 'entregue';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Preparing => 'Em separação',
            self::ReadyForPickup => 'Pronto para retirada',
            self::OutForDelivery => 'Saiu para entrega',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Pagamento já confirmado (qualquer etapa depois do pagamento, exceto cancelado). */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Preparing, self::ReadyForPickup, self::OutForDelivery, self::Delivered], true);
    }

    /**
     * Próximos status permitidos para a loja, conforme a forma de entrega.
     *
     * @return list<self>
     */
    public function nextFor(DeliveryMethod $delivery): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Cancelled],
            self::Paid => [self::Preparing, self::Cancelled],
            self::Preparing => [
                $delivery === DeliveryMethod::Pickup ? self::ReadyForPickup : self::OutForDelivery,
                self::Cancelled,
            ],
            self::ReadyForPickup, self::OutForDelivery => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }
}
