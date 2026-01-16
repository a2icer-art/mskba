<?php

use App\Domain\Balances\Enums\BalanceStatus;
use App\Domain\Payments\Enums\PaymentCurrency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('available_amount')->default(0);
            $table->unsignedBigInteger('held_amount')->default(0);
            $table->string('currency', 3)->default(PaymentCurrency::Rub->value);
            $table->string('status', 20)->default(BalanceStatus::Active->value);
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
