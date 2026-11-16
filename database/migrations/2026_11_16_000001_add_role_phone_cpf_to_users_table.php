<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('cliente')->after('password')->index();
            $table->string('phone', 20)->nullable()->after('email');
            // Só dígitos (11). Exigido pelo Asaas no checkout; nulo até a primeira compra.
            $table->string('cpf', 11)->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'cpf']);
        });
    }
};
