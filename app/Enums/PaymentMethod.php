<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case CreditCard = 'cartao';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::CreditCard => 'Cartão de crédito',
        };
    }

    /** billingType do Asaas */
    public function asaasBillingType(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::CreditCard => 'CREDIT_CARD',
        };
    }
}
