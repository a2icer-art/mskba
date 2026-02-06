<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['owner_type', 'owner_id', 'is_default']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('user_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });

        $userType = User::class;
        $defaultMethodIds = DB::table('payment_methods')
            ->select('owner_id', DB::raw('MIN(id) as id'))
            ->where('owner_type', $userType)
            ->groupBy('owner_id')
            ->pluck('id')
            ->all();

        if ($defaultMethodIds !== []) {
            DB::table('payment_methods')
                ->whereIn('id', $defaultMethodIds)
                ->update(['is_default' => true]);
        }

        $contracts = DB::table('contracts')->get(['id', 'user_id']);
        foreach ($contracts as $contract) {
            $defaultMethodId = DB::table('payment_methods')
                ->where('owner_type', $userType)
                ->where('owner_id', $contract->user_id)
                ->where('is_default', true)
                ->value('id');

            if ($defaultMethodId) {
                DB::table('contracts')
                    ->where('id', $contract->id)
                    ->update(['payment_method_id' => $defaultMethodId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['owner_type', 'owner_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
