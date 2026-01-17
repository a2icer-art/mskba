<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('context_type', 255)->nullable()->after('created_by');
            $table->unsignedBigInteger('context_id')->nullable()->after('context_type');
            $table->string('context_label', 255)->nullable()->after('context_id');
            $table->index(['context_type', 'context_id'], 'conversations_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_context_index');
            $table->dropColumn(['context_type', 'context_id', 'context_label']);
        });
    }
};
