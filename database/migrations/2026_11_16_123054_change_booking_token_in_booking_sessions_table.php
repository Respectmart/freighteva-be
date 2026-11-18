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
        Schema::table('booking_sessions', function (Blueprint $table) {
            $table->dropIndex(['booking_token']);
            $table->text('booking_token')->nullable()->change();
            $table->string('token_hash', 64)->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_sessions', function (Blueprint $table) {
            $table->dropColumn('token_hash');
            $table->string('booking_token')->nullable()->change();
            $table->index('booking_token');
        });
    }
};
