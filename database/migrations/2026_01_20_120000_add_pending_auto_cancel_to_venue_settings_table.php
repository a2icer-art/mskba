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
            $table->unsignedInteger('pending_review_minutes')
                ->default(VenueSettings::DEFAULT_PENDING_REVIEW_MINUTES);
            $table->unsignedInteger('pending_before_start_minutes')
                ->default(VenueSettings::DEFAULT_PENDING_BEFORE_START_MINUTES);
            $table->unsignedInteger('pending_warning_minutes')
                ->default(VenueSettings::DEFAULT_PENDING_WARNING_MINUTES);
        });
    }

    public function down(): void
    {
        Schema::table('venue_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pending_review_minutes',
                'pending_before_start_minutes',
                'pending_warning_minutes',
            ]);
        });
    }
};
