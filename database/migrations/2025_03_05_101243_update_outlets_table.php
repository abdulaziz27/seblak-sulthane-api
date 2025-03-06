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
        Schema::table('outlets', function (Blueprint $table) {
            // First drop existing 'address' column
            $table->dropColumn('address');

            // Add new columns
            $table->string('address1')->after('name');
            $table->string('address2')->nullable()->after('address1');
            $table->string('leader')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('leader');

            // Modify phone to be nullable
            $table->string('phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn(['address1', 'address2', 'leader', 'notes']);

            // Add back the original address column
            $table->text('address')->after('name');

            // Make phone required again
            $table->string('phone')->nullable(false)->change();
        });
    }
};
