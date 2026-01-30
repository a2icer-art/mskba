<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('alias', 120);
            $table->boolean('is_custom')->default(false);
            $table->foreignId('venue_id')->nullable()->constrained('venues');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['venue_id', 'alias', 'is_custom']);
            $table->unique(['venue_id', 'name', 'is_custom']);
            $table->index(['is_custom', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
