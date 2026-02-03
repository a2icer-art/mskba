<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->unsignedInteger('payment_confirmation_before_start_minutes')
                ->default(0)
                ->after('pending_warning_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn('payment_confirmation_before_start_minutes');
        });
    }
};
