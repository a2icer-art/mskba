<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_allow_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('allowed_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'allowed_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_allow_lists');
    }
};
