<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_verifications', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('code');
            $table->index('token_hash', 'idx_contact_verifications_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('contact_verifications', function (Blueprint $table) {
            $table->dropIndex('idx_contact_verifications_token_hash');
            $table->dropColumn('token_hash');
        });
    }
};
