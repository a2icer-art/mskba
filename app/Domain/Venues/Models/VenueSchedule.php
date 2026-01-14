<?php

namespace App\Domain\Venues\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'timezone',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(VenueScheduleInterval::class, 'schedule_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(VenueScheduleException::class, 'schedule_id');
    }
}
