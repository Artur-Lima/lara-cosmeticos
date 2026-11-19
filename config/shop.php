<?php

/*
 * Dados públicos da loja exibidos na vitrine. Preencha no .env.
 */
return [
    'name' => env('APP_NAME', 'Lara Lima Cosméticos'),

    // Demonstração: faixa de aviso no topo e site fora do Google (noindex + robots).
    'demo' => (bool) env('SHOP_DEMO', false),

    // Só dígitos, com DDI e DDD: 5569999999999
    'whatsapp' => env('SHOP_WHATSAPP'),

    'instagram' => env('SHOP_INSTAGRAM'), // sem @

    // Endereço para retirada na loja
    'address' => env('SHOP_ADDRESS', 'Itapuã do Oeste – RO'),

    'opening_hours' => env('SHOP_OPENING_HOURS'),

    // Limite por item no carrinho (evita compra de atacado por engano)
    'max_qty_per_item' => (int) env('SHOP_MAX_QTY_PER_ITEM', 10),
];
