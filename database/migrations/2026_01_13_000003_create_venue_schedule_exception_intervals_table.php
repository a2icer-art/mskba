<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_schedule_exception_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exception_id')
                ->constrained('venue_schedule_exceptions')
                ->cascadeOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index('exception_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_schedule_exception_intervals');
    }
};
