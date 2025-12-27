<?php

namespace App\Domain\Moderation\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationRequest extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'status',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => ModerationEntityType::class,
            'status' => ModerationStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
