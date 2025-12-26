<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('contact_id')->constrained('user_contacts');
            $table->string('code', 20);
            $table->dateTime('expires_at');
            $table->dateTime('sent_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contact_id', 'verified_at'], 'idx_contact_verifications_contact_verified');
            $table->index(['user_id', 'expires_at'], 'idx_contact_verifications_user_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_verifications');
    }
};
