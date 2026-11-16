<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('barcode', 20)->nullable()->index();
            $table->string('label', 80); // "100 ml", "Nude 02"
            $table->unsignedSmallInteger('volume_ml')->nullable();
            $table->string('shade', 60)->nullable();
            $table->char('color_hex', 7)->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable(); // preço "de"
            // Unsigned: o banco recusa estoque negativo mesmo se a aplicação errar.
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_g')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            // Soft delete: itens de pedidos antigos continuam apontando para a variante.
            $table->softDeletes();

            $table->index(['product_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
