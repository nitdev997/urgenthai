<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('fullname')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('avatar')->nullable();
                $table->date('dob')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->boolean('account_status')->default(1); // 1 for activated, 0 for deactivated
                $table->string('otp')->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->string('dl_number')->nullable(); // driver’s license number
                $table->string('dl_image')->nullable(); // driver’s license image
                $table->string('country')->nullable();
                $table->string('number_plate_no')->nullable();
                $table->string('number_plate_image')->nullable();
                $table->timestamp('admin_verify_at')->nullable();
                $table->enum('document_verification_status', ['Pending', 'Complete'])->default('Pending');
                $table->string('status', 60)->default('published');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('drivers_translations')) {
            Schema::create('drivers_translations', function (Blueprint $table) {
                $table->string('lang_code');
                $table->foreignId('drivers_id');
                $table->string('name', 255)->nullable();

                $table->primary(['lang_code', 'drivers_id'], 'drivers_translations_primary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('drivers_translations');
    }
};
