<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_cash', function (Blueprint $table) {
            $table->integer('opening_balance')->default(0)->change();
            $table->integer('expenses')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('daily_cash', function (Blueprint $table) {
            // Kembalikan ke tipe data sebelumnya jika perlu
            $table->decimal('opening_balance', 15, 2)->default(0)->change();
            $table->decimal('expenses', 15, 2)->default(0)->change();
        });
    }
};
