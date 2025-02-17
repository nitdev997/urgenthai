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
        Schema::table('ec_products', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->default(0)->check('rating >= 0.0 AND rating <= 5.0');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            //
        });
    }
};
