<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'changes',
        'actor_id',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
