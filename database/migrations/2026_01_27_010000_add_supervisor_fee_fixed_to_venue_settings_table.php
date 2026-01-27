<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->unsignedInteger('supervisor_fee_amount_rub')->default(0);
            $table->boolean('supervisor_fee_is_fixed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn('supervisor_fee_amount_rub');
            $table->dropColumn('supervisor_fee_is_fixed');
        });
    }
};
