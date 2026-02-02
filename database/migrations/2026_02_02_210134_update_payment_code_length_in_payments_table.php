<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_code VARCHAR(12) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_code VARCHAR(6) NULL");
    }
};
