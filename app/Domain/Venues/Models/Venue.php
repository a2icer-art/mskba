<?php

namespace App\Domain\Venues\Models;

use App\Domain\Venues\Enums\VenueStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'alias',
        'status',
        'created_by',
        'updated_by',
        'confirmed_at',
        'confirmed_by',
        'venue_type_id',
        'address',
        'address_id',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VenueStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function venueType(): BelongsTo
    {
        return $this->belongsTo(VenueType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
