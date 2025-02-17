<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->string('driver')->default('25');
                $table->string('vendor')->default('1');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commissions_translations')) {
            Schema::create('commissions_translations', function (Blueprint $table) {
                $table->string('lang_code');
                $table->foreignId('commissions_id');
                $table->string('name', 255)->nullable();

                $table->primary(['lang_code', 'commissions_id'], 'commissions_translations_primary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commissions_translations');
    }
};
