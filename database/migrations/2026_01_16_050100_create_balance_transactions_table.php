<?php

use App\Domain\Balances\Enums\BalanceTransactionType;
use App\Domain\Payments\Enums\PaymentCurrency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')
                ->constrained('balances')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('type', 20)->default(BalanceTransactionType::TopUp->value);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 3)->default(PaymentCurrency::Rub->value);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
    }
};
