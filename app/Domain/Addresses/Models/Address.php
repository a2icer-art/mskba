<?php

namespace App\Domain\Addresses\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Metros\Models\Metro;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'city',
        'metro_id',
        'street',
        'building',
        'str_address',
        'created_by',
        'updated_by',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function metro(): BelongsTo
    {
        return $this->belongsTo(Metro::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayAddressAttribute(): string
    {
        if ($this->str_address) {
            return $this->str_address;
        }

        $parts = array_filter([
            $this->city,
            $this->street,
            $this->building,
        ]);

        return implode(', ', $parts);
    }
}
