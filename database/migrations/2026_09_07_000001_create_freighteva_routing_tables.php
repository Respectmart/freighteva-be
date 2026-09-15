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
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('tenants', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('tenants', 'country_code')) {
                $table->string('country_code', 5)->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('tenants', 'zone_code')) {
                $table->string('zone_code', 50)->nullable()->after('country_code');
            }
            if (!Schema::hasColumn('tenants', 'metro_area')) {
                $table->string('metro_area', 100)->nullable()->after('zone_code');
            }
            if (!Schema::hasColumn('tenants', 'service_radius_km')) {
                $table->integer('service_radius_km')->default(25)->after('metro_area');
            }
            if (!Schema::hasColumn('tenants', 'is_verified')) {
                $table->boolean('is_verified')->default(true)->after('service_radius_km');
            }
            if (!Schema::hasColumn('tenants', 'rating_score')) {
                $table->decimal('rating_score', 3, 2)->default(4.80)->after('is_verified');
            }
            if (!Schema::hasColumn('tenants', 'booking_success_rate')) {
                $table->decimal('booking_success_rate', 5, 2)->default(98.50)->after('rating_score');
            }
        });

        if (!Schema::hasTable('merchant_shipping_capabilities')) {
            Schema::create('merchant_shipping_capabilities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('origin_country_code', 5)->index();
                $table->string('destination_country_code', 5)->index();
                $table->string('destination_city', 100)->nullable()->index();
                $table->string('freight_mode', 20)->default('air'); // 'air', 'ocean', 'both'
                $table->decimal('min_weight_kg', 8, 2)->default(0.10);
                $table->decimal('max_weight_kg', 8, 2)->default(1000.00);
                $table->boolean('pickup_available')->default(true);
                $table->boolean('dropoff_available')->default(true);
                $table->decimal('base_rate_per_kg', 10, 2)->default(10.00);
                $table->integer('min_transit_days')->default(3);
                $table->integer('max_transit_days')->default(7);
                $table->integer('corridor_specialization_tier')->default(3); // 1 to 5
                $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('merchant_shipping_capabilities');

        Schema::table('tenants', function (Blueprint $table) {
            $columnsToDrop = [
                'latitude',
                'longitude',
                'country_code',
                'zone_code',
                'metro_area',
                'service_radius_km',
                'is_verified',
                'rating_score',
                'booking_success_rate'
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
