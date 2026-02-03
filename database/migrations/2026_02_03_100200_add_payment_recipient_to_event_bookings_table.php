<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->string('payment_recipient_type')->nullable()->after('payment_order_snapshot');
            $table->unsignedBigInteger('payment_recipient_id')->nullable()->after('payment_recipient_type');
            $table->string('payment_recipient_label')->nullable()->after('payment_recipient_id');
            $table->json('payment_methods_snapshot')->nullable()->after('payment_recipient_label');
            $table->index(['payment_recipient_type', 'payment_recipient_id'], 'event_bookings_payment_recipient_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->dropIndex('event_bookings_payment_recipient_idx');
            $table->dropColumn([
                'payment_recipient_type',
                'payment_recipient_id',
                'payment_recipient_label',
                'payment_methods_snapshot',
            ]);
        });
    }
};
