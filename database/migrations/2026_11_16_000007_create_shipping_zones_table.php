<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entrega própria apenas em Itapuã do Oeste: cada zona é um bairro/região
     * da cidade com taxa própria. Retirada na loja não usa zona.
     */
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->decimal('fee', 8, 2);
            $table->decimal('free_above', 10, 2)->nullable();
            $table->string('estimate', 60)->nullable(); // "até 2h", "no mesmo dia"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
