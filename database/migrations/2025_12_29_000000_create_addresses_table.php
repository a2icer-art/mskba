<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('city');
            $table->string('district')->nullable();
            $table->unsignedBigInteger('metro_id')->nullable();
            $table->string('street');
            $table->string('building');
            $table->string('str_address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
