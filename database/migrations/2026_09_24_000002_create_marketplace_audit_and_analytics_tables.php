<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 6 (Analytics, Metrics & Audit Logging Engine).
     */
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_audit_logs')) {
            Schema::create('marketplace_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('actor_type', 32)->default('system'); // system, merchant, admin, customer
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('event_type', 64)->index(); // quote_generated, booking_created, merchant_accepted, merchant_rejected, status_updated, exception_reassigned, endorsement_updated
                $table->string('entity_type', 64)->index(); // quote, shipment, tenant, exception, tracking
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_audit_logs');
    }
};
