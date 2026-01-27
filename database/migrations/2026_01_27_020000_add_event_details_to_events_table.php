<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('participants_limit')
                ->default(0)
                ->after('timezone');
            $table->unsignedInteger('price_amount_minor')
                ->default(0)
                ->after('participants_limit');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['participants_limit', 'price_amount_minor']);
        });
    }
};
