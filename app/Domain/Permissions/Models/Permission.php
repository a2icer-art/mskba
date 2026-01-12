<?php

namespace App\Domain\Permissions\Models;

use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Users\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'scope',
        'target_model',
    ];

    protected function casts(): array
    {
        return [
            'scope' => PermissionScope::class,
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }
}