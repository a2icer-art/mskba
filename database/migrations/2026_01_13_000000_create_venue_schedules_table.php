<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->string('timezone')->default('UTC+3');
            $table->timestamps();

            $table->unique('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_schedules');
    }
};
