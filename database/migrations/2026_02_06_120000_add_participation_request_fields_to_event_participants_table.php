<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->string('requested_status')->nullable()->after('status');
            $table->string('request_source')->nullable()->after('requested_status');
            $table->foreignId('requested_by')
                ->nullable()
                ->after('request_source')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn(['requested_status', 'request_source']);
        });
    }
};
