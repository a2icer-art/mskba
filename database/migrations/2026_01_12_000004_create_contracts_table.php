<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('status');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'entity_type', 'entity_id']);
            $table->unique(['user_id', 'entity_type', 'entity_id', 'status'], 'contracts_unique_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
