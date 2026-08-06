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
            $table->decimal('rating_avg', 2, 1)->default(0.0)->after('status');
            $table->integer('rating_count')->default(0)->after('rating_avg');
            $table->enum('sub_type', ['starter', 'intermediate', 'advanced'])->default('starter')->after('rating_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count', 'sub_type']);
        });
    }
};
