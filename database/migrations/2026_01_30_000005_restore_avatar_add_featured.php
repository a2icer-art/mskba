<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (!Schema::hasColumn('media', 'is_avatar')) {
                $table->boolean('is_avatar')->default(false)->after('description');
            }
            if (!Schema::hasColumn('media', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_avatar');
            }
        });

        if (Schema::hasColumn('media', 'collection')) {
            DB::table('media')
                ->where('collection', 'avatar')
                ->update(['is_avatar' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (Schema::hasColumn('media', 'is_avatar')) {
                $table->dropColumn('is_avatar');
            }
            if (Schema::hasColumn('media', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
        });
    }
};
