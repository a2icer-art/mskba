<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_code', 6)->nullable()->unique();
        });

        $existingIds = DB::table('payments')
            ->whereNull('payment_code')
            ->pluck('id');

        foreach ($existingIds as $id) {
            $code = $this->generateUniqueCode();
            DB::table('payments')
                ->where('id', $id)
                ->update(['payment_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['payment_code']);
            $table->dropColumn('payment_code');
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $exists = DB::table('payments')
                ->where('payment_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
};
