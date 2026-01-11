<?php

namespace App\Domain\Metros\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Metro extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'alias',
        'line_name',
        'line_color',
        'city',
        'status',
        'commentary',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeForOptions(Builder $query): Builder
    {
        return $query
            ->where('status', 1)
            ->orderBy('city')
            ->orderBy('line_name')
            ->orderBy('name')
            ->select(['id', 'name', 'line_name', 'line_color', 'city']);
    }
}
