<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('registered_via', ['site', 'tg_link', 'email_link', 'other'])
                ->default('site')
                ->after('login');
            $table->json('registration_details')->nullable()->after('registered_via');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registered_via', 'registration_details']);
        });
    }
};
