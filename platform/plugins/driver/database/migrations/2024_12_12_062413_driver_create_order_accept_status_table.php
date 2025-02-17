<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('order_accept_status')) {
            Schema::create('order_accept_status', function (Blueprint $table) {
                $table->id();
                $table->integer('order_id');
                $table->integer('driver_id');
                $table->integer('status')->default(0)->comment('0 rejected, 1 accepted');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_accept_status');
    }
};
