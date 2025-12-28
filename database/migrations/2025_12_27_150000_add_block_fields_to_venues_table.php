<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dateTime('blocked_at')->nullable()->after('confirmed_by');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete()->after('blocked_at');
            $table->string('block_reason')->nullable()->after('blocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['blocked_at', 'block_reason']);
        });
    }
};
