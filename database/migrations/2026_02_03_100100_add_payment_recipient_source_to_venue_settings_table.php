<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->string('payment_recipient_source')->nullable()->after('payment_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn('payment_recipient_source');
        });
    }
};
