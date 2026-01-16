<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
        });

        DB::table('payment_orders')->insert([
            ['code' => 'prepayment', 'label' => 'Предоплата'],
            ['code' => 'partial_prepayment', 'label' => 'Частичная предоплата'],
            ['code' => 'postpayment', 'label' => 'Постоплата'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};