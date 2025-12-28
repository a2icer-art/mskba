<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('commentary');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete()->after('blocked_at');
            $table->text('block_reason')->nullable()->after('blocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropColumn(['blocked_at', 'blocked_by', 'block_reason']);
        });
    }
};
