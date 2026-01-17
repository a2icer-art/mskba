<?php

use App\Domain\Venues\Models\VenueSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_wait_minutes')
                ->default(VenueSettings::DEFAULT_PAYMENT_WAIT_MINUTES);
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn('payment_wait_minutes');
        });
    }
};
