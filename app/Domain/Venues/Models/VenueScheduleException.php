<?php

namespace App\Domain\Venues\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueScheduleException extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'date',
        'is_closed',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(VenueSchedule::class, 'schedule_id');
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(VenueScheduleExceptionInterval::class, 'exception_id');
    }
}
