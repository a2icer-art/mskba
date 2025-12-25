<?php

namespace App\Domain\Participants\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaProfile extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    protected $table = 'participant_media_profiles';

    protected $fillable = [
        'participant_role_assignment_id',
        'specialization',
        'portfolio_url',
        'channels',
        'experience_from',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'experience_from' => 'date',
            'channels' => 'array',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ParticipantRoleAssignment::class, 'participant_role_assignment_id');
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
}
