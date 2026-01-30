<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('media', 'is_avatar')) {
            DB::table('media')
                ->where('is_avatar', true)
                ->update(['collection' => 'avatar']);

            Schema::table('media', function (Blueprint $table) {
                $table->dropColumn('is_avatar');
            });
        }
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->boolean('is_avatar')->default(false)->after('description');
        });

        DB::table('media')
            ->where('collection', 'avatar')
            ->update(['is_avatar' => true]);
    }
};
