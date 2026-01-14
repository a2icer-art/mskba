<?php

namespace App\Domain\Venues\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueScheduleInterval extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(VenueSchedule::class, 'schedule_id');
    }
}
