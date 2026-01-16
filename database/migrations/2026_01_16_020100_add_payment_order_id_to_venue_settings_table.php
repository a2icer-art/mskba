<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->foreignId('payment_order_id')
                ->nullable()
                ->constrained('payment_orders')
                ->nullOnDelete();
        });

        $orders = DB::table('payment_orders')->pluck('id', 'code');
        $settings = DB::table('venue_settings')->get(['id', 'payment_order']);
        foreach ($settings as $row) {
            $code = $row->payment_order ?: 'prepayment';
            $orderId = $orders[$code] ?? null;
            if ($orderId) {
                DB::table('venue_settings')->where('id', $row->id)->update([
                    'payment_order_id' => $orderId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_order_id');
        });
    }
};