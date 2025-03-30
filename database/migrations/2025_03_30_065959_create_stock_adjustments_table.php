<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained();
            $table->integer('quantity');  // Positif untuk penambahan, negatif untuk pengurangan
            $table->integer('purchase_price')->nullable(); // Hanya diisi jika quantity positif
            $table->date('adjustment_date');
            $table->enum('adjustment_type', ['purchase', 'usage', 'damage', 'other'])->default('purchase');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
