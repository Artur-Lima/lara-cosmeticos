<?php

namespace App\Support;

final class Money
{
    /** 1234.5 -> "R$ 1.234,50" */
    public static function brl(float|int|string|null $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
