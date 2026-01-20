<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('sender_id');
            $table->string('link_url', 255)->nullable()->after('body');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE messages MODIFY sender_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE messages MODIFY sender_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['title', 'link_url']);
        });
    }
};
