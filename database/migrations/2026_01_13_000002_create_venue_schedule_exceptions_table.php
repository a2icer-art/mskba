<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('venue_schedules')
                ->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_schedule_exceptions');
    }
};
