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
            $table->unsignedSmallInteger('rental_duration_minutes')
                ->default(VenueSettings::DEFAULT_RENTAL_DURATION_MINUTES);
            $table->unsignedInteger('rental_price_rub')
                ->default(VenueSettings::DEFAULT_RENTAL_PRICE_RUB);
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn(['rental_duration_minutes', 'rental_price_rub']);
        });
    }
};
