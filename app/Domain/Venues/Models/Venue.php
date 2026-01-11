<?php

namespace App\Domain\Venues\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Addresses\Models\Address;
use App\Domain\Venues\Builders\VenueQueryBuilder;
use App\Domain\Venues\Enums\VenueStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'alias',
        'status',
        'is_available',
        'closure_reason',
        'created_by',
        'updated_by',
        'confirmed_at',
        'confirmed_by',
        'venue_type_id',
        'str_address',
        'commentary',
        'blocked_at',
        'blocked_by',
        'block_reason',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VenueStatus::class,
            'is_available' => 'boolean',
            'confirmed_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function venueType(): BelongsTo
    {
        return $this->belongsTo(VenueType::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function latestAddress(): HasOne
    {
        return $this->hasOne(Address::class)->latestOfMany();
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

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function getRouteKeyName(): string
    {
        return 'alias';
    }

    public function newEloquentBuilder($query): Builder
    {
        return new VenueQueryBuilder($query);
    }
}
