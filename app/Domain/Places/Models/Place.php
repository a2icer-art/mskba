<?php

namespace App\Domain\Places\Models;

use App\Domain\Places\Enums\PlaceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
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
        'place_type_id',
        'address',
        'address_id',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlaceStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function placeType(): BelongsTo
    {
        return $this->belongsTo(PlaceType::class);
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
