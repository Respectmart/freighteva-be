<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Merchant Endorsement & Recommendation Configs.
     * Note: tenants table is kept clean without extra columns by using a separate 1:1 merchant_endorsements table.
     */
    public function up(): void
    {
        // 1. Remove any previously added endorsement columns from tenants table to keep it clean
        Schema::table('tenants', function (Blueprint $table) {
            $columnsToDrop = [
                'is_endorsed',
                'endorsement_status',
                'endorsed_at',
                'endorsement_expiry',
                'endorsed_by',
                'endorsement_notes',
                'endorsement_tier',
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 2. Create isolated merchant_endorsements table (1-to-1 with tenants)
        if (!Schema::hasTable('merchant_endorsements')) {
            Schema::create('merchant_endorsements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique()->index();
                $table->boolean('is_endorsed')->default(false)->index();
                $table->string('status', 20)->default('PENDING')->index(); // PENDING, ENDORSED, SUSPENDED, EXPIRED, REJECTED
                $table->tinyInteger('tier')->default(3); // 1 (Starter) to 5 (Elite)
                $table->timestamp('endorsed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('endorsed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 3. Create recommendation_configs table
        if (!Schema::hasTable('recommendation_configs')) {
            Schema::create('recommendation_configs', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->json('value');
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });

            // Seed default configurable weights and naming conventions
            DB::table('recommendation_configs')->insert([
                [
                    'key' => 'scoring_weights',
                    'value' => json_encode([
                        'speed' => 25.0,
                        'cost' => 25.0,
                        'trust' => 20.0,
                        'convenience' => 15.0,
                        'flexibility' => 15.0,
                    ]),
                    'description' => 'Multi-factor weighted scoring percentage distribution (Speed 25%, Cost 25%, Trust 20%, Convenience 15%, Flexibility 15%).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'service_names',
                    'value' => json_encode([
                        'swift' => 'Freighteva Swift',
                        'trusted' => 'Freighteva Trusted',
                        'savers' => 'Freighteva Savers',
                        'flexible' => 'Freighteva Flexible',
                        'convenient' => 'Freighteva Convenient',
                    ]),
                    'description' => 'Dynamic Freighteva service identity naming templates assigned to top scoring partner profiles.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'quote_ttl_minutes',
                    'value' => json_encode(15),
                    'description' => 'Configurable validity duration in minutes for recommendation quotes before expiration.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'max_recommendations',
                    'value' => json_encode(5),
                    'description' => 'Maximum number of partner recommendations returned to customer per search query.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // 4. Seed baseline endorsement records for existing tenants
        $tenants = DB::table('tenants')->get();
        foreach ($tenants as $tenant) {
            $isEndorsed = ($tenant->status === 'active' || $tenant->active);
            DB::table('merchant_endorsements')->updateOrInsert(
                ['tenant_id' => $tenant->id],
                [
                    'is_endorsed' => $isEndorsed,
                    'status' => $isEndorsed ? 'ENDORSED' : 'PENDING',
                    'tier' => 4,
                    'endorsed_at' => $isEndorsed ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_endorsements');
        Schema::dropIfExists('recommendation_configs');
    }
};
