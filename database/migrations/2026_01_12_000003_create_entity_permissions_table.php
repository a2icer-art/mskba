<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entity_permissions')) {
            Schema::table('entity_permissions', function (Blueprint $table) {
                $table->unique(
                    ['permission_id', 'user_id', 'entity_type', 'entity_id'],
                    'entity_permissions_unique'
                );
            });

            return;
        }

        Schema::create('entity_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->unique(
                ['permission_id', 'user_id', 'entity_type', 'entity_id'],
                'entity_permissions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_permissions');
    }
};
