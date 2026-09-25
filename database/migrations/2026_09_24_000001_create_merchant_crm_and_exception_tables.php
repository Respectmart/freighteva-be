<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 5: Merchant CRM Integration & Exception Engine.
     */
    public function up(): void
    {
        // 1. Shipment Tracking Events Table
        if (!Schema::hasTable('shipment_tracking_events')) {
            Schema::create('shipment_tracking_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id')->index();
                $table->string('status', 50)->index(); // BOOKED, MERCHANT_RECEIVED, ACCEPTED, PICKUP_SCHEDULED, PICKED_UP, IN_TRANSIT, ARRIVED, DELIVERED, EXCEPTION_REASSIGNED, REJECTED
                $table->string('title', 150);
                $table->text('description')->nullable();
                $table->string('location', 150)->nullable();
                $table->timestamp('event_time')->useCurrent();
                $table->string('actor_type', 30)->default('system'); // system, merchant, customer, admin
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            });
        }

        // 2. Merchant Booking Exceptions Table
        if (!Schema::hasTable('merchant_booking_exceptions')) {
            Schema::create('merchant_booking_exceptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id')->index();
                $table->unsignedBigInteger('original_tenant_id')->index();
                $table->unsignedBigInteger('reassigned_tenant_id')->nullable()->index();
                $table->string('reason_code', 60)->index(); // CAPACITY_FULL, SCHEDULE_CONFLICT, UNSUPPORTED_CARGO, PRICE_MISMATCH, OPERATIONAL_DELAY, OTHER
                $table->text('reason_notes')->nullable();
                $table->boolean('penalty_applied')->default(true);
                $table->decimal('penalty_points', 5, 2)->default(5.00);
                $table->string('resolution_status', 40)->default('REASSIGNED')->index(); // REASSIGNED, REFUND_PENDING, CANCELLED, MANUAL_REVIEW
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
                $table->foreign('original_tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('reassigned_tenant_id')->references('id')->on('tenants')->onDelete('set null');
            });
        }

        // 3. Merchant Performance Logs Table
        if (!Schema::hasTable('merchant_performance_logs')) {
            Schema::create('merchant_performance_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('metric_type', 60)->index(); // acceptance, rejection_penalty, on_time_delivery, claim_filed
                $table->decimal('impact_score', 6, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_performance_logs');
        Schema::dropIfExists('merchant_booking_exceptions');
        Schema::dropIfExists('shipment_tracking_events');
    }
};
