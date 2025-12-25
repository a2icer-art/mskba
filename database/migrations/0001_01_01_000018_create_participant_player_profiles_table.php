<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_role_assignment_id');
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('position')->nullable();
            $table->string('dominant_hand')->nullable();
            $table->date('experience_from')->nullable();
            $table->json('skills')->nullable();
            $table->text('achievements')->nullable();
            $table->unsignedSmallInteger('rating')->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('participant_role_assignment_id', 'uq_part_player_assignment');
            $table->foreign('participant_role_assignment_id', 'fk_part_player_assignment')
                ->references('id')
                ->on('participant_role_assignments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_player_profiles');
    }
};
