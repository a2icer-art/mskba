<?php

use App\Domain\Payments\Enums\PaymentCurrency;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->nullableMorphs('payable');
            $table->foreignId('payment_order_id')
                ->nullable()
                ->constrained('payment_orders')
                ->nullOnDelete();
            $table->json('payment_order_snapshot')->nullable();
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default(PaymentCurrency::Rub->value);
            $table->string('status', 20)->default(PaymentStatus::Created->value);
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
