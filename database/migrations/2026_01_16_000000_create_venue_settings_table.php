<?php

use App\Domain\Venues\Models\VenueSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('booking_lead_time_minutes')
                ->default(VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES);
            $table->unsignedSmallInteger('booking_min_interval_minutes')
                ->default(VenueSettings::DEFAULT_BOOKING_MIN_INTERVAL_MINUTES);
            $table->string('payment_order')
                ->default(VenueSettings::DEFAULT_PAYMENT_ORDER->value);
            $table->timestamps();

            $table->unique('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_settings');
    }
};
