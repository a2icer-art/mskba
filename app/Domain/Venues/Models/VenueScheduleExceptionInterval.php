<?php

namespace App\Domain\Venues\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueScheduleExceptionInterval extends Model
{
    use HasFactory;

    protected $fillable = [
        'exception_id',
        'starts_at',
        'ends_at',
    ];

    public function exception(): BelongsTo
    {
        return $this->belongsTo(VenueScheduleException::class, 'exception_id');
    }
}
