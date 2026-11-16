<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('short_description', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('how_to_use')->nullable();
            $table->text('composition')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('status', 12)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Vitrine sempre filtra por status primeiro.
            $table->index(['status', 'category_id']);
            $table->index(['status', 'brand_id']);
            $table->index(['status', 'is_featured']);
        });

        // Busca textual: só MySQL/MariaDB (SQLite dos testes não suporta).
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'short_description']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
