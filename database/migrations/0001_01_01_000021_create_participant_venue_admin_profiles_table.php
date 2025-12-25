<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_venue_admin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_role_assignment_id');
            $table->text('venue_management')->nullable();
            $table->json('certifications')->nullable();
            $table->date('experience_from')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('participant_role_assignment_id', 'uq_part_venue_admin_assignment');
            $table->foreign('participant_role_assignment_id', 'fk_part_venue_admin_assignment')
                ->references('id')
                ->on('participant_role_assignments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_venue_admin_profiles');
    }
};
