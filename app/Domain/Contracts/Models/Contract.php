<?php

namespace App\Domain\Contracts\Models;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Permissions\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contract extends Model
{
    protected $fillable = [
        'user_id',
        'name',
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'contract_permissions')
            ->withTimestamps();
    }
}
