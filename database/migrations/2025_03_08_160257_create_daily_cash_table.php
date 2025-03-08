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
        Schema::create('daily_cash', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets');
            $table->foreignId('user_id')->constrained('users');
            $table->date('date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('expenses', 15, 2)->default(0);
            $table->text('expenses_note')->nullable();
            $table->timestamps();

            // Memastikan hanya ada satu record per outlet per hari
            $table->unique(['outlet_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_cash');
    }
};
