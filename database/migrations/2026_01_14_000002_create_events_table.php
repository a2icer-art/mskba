<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')
                ->constrained('event_types')
                ->cascadeOnDelete();
            $table->foreignId('organizer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('tournament_id')
                ->nullable()
                ->constrained('tournaments')
                ->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('title')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('UTC+3');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
