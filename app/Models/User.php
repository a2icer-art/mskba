<?php

namespace App\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\UserEmail;
use App\Domain\Users\Models\UserProfile;
use App\Domain\Users\Models\UserRole;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Auditable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'login',
        'registered_via',
        'registration_details',
        'password',
        'status',
        'confirmed_at',
        'confirmed_by',
        'commentary',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'confirmed_at' => 'datetime',
            'confirmed_by' => UserConfirmedBy::class,
            'registered_via' => UserRegisteredVia::class,
            'registration_details' => 'array',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deleted_by');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(UserEmail::class);
    }

    public function participantRoleAssignments(): HasMany
    {
        return $this->hasMany(ParticipantRoleAssignment::class);
    }
}
