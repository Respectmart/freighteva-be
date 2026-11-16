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
        if (!Schema::hasTable('kyc_verifications')) {
            Schema::create('kyc_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('session_id')->unique();
                $table->string('status')->default('Not Started');
                $table->json('decision_payload')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('booking_sessions')) {
            Schema::create('booking_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('booking_token')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->index();
                $table->string('customer_phone')->nullable();
                $table->string('route_from')->nullable();
                $table->string('route_to')->nullable();
                $table->json('cargo_details')->nullable();
                $table->decimal('base_price', 10, 2)->default(0);
                $table->decimal('protection_fee', 10, 2)->default(0);
                $table->decimal('total_price', 10, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->unsignedTinyInteger('current_step')->default(1);
                $table->boolean('is_kyc_verified')->default(false);
                $table->string('status', 30)->default('pending'); // pending, completed, abandoned
                $table->unsignedBigInteger('portal_user_id')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_sessions');
        Schema::dropIfExists('kyc_verifications');
    }
};
