<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['unconfirmed', 'confirmed'])->default('unconfirmed');
            $table->timestamp('confirmed_at')->nullable();
            $table->enum('confirmed_by', ['admin', 'email', 'phone', 'telegram', 'vk', 'other'])->nullable();
            $table->text('commentary')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'confirmed_at', 'confirmed_by', 'commentary']);
        });
    }
};
