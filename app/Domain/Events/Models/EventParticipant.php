<?php

namespace App\Domain\Events\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Events\Enums\EventParticipantRole;
use App\Domain\Events\Enums\EventParticipantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'status',
        'status_change_reason',
        'user_status_reason',
        'status_changed_by',
        'status_changed_at',
        'joined_at',
        'invited_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventParticipantRole::class,
            'status' => EventParticipantStatus::class,
            'joined_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
