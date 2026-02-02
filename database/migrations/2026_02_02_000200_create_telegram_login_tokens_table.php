<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('session_id', 255)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('telegram_id', 64)->nullable();
            $table->string('telegram_username', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['expires_at'], 'idx_telegram_login_tokens_expires');
            $table->index(['telegram_id'], 'idx_telegram_login_tokens_telegram');
            $table->index(['session_id'], 'idx_telegram_login_tokens_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_login_tokens');
    }
};
