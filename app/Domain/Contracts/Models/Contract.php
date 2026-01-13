<?php

namespace App\Domain\Contracts\Models;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Permissions\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contract extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'name',
        'contract_type',
        'entity_type',
        'entity_id',
        'starts_at',
        'ends_at',
        'status',
        'comment',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => ContractStatus::class,
        'contract_type' => ContractType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'contract_permissions')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
