<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('asaas_customer_id', 40)->nullable()->unique()->after('cpf');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // público: LL-7K2QXM
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('aguardando_pagamento');
            $table->string('delivery_method', 12);
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            // Cópias do momento da compra: mudanças posteriores não alteram o pedido.
            $table->json('customer');
            $table->json('address')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 8, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 12);
            $table->string('asaas_payment_id', 40)->nullable()->unique();
            $table->string('payment_url')->nullable();
            $table->text('pix_payload')->nullable();
            $table->mediumText('pix_qr_image')->nullable(); // PNG base64 devolvido pelo Asaas
            $table->timestamp('pix_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_attention')->default(false);
            $table->string('attention_reason', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('needs_attention');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 180);
            $table->string('variant_label', 80);
            $table->string('sku', 60);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total', 10, 2);
            // true quando o estoque foi baixado na confirmação; só esses voltam no cancelamento.
            $table->boolean('stock_deducted')->default(false);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // nulo = sistema/Asaas
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('asaas_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 120)->unique(); // idempotência: reentregas têm o mesmo id
            $table->string('event', 60);
            $table->string('payment_id', 40)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('result', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_webhook_events');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['asaas_customer_id']);
            $table->dropColumn('asaas_customer_id');
        });
    }
};
