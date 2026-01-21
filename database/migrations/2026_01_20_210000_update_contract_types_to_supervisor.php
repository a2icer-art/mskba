<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contracts')
            ->whereIn('contract_type', ['manager', 'controller'])
            ->update(['contract_type' => 'supervisor']);
    }

    public function down(): void
    {
        DB::table('contracts')
            ->where('contract_type', 'supervisor')
            ->update(['contract_type' => 'manager']);
    }
};
