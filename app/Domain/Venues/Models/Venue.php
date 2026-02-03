<?php

namespace App\Domain\Venues\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Addresses\Models\Address;
use App\Domain\Media\Models\Media;
use App\Domain\Venues\Builders\VenueQueryBuilder;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Amenity;
use App\Domain\Venues\Models\VenueAmenity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    public function schedule(): HasOne
    {
        return $this->hasOne(VenueSchedule::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(VenueSettings::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function paymentMethods(): MorphMany
    {
        return $this->morphMany(\App\Domain\Payments\Models\PaymentMethod::class, 'owner');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'venue_amenities')
            ->withPivot(['note', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    public function venueAmenities(): HasMany
    {
        return $this->hasMany(VenueAmenity::class);
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
