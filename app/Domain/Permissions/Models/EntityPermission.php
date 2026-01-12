<?php

namespace App\Domain\Permissions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntityPermission extends Model
{
    use HasFactory;

    protected $table = 'entity_permissions';

    protected $fillable = [
        'permission_id',
        'user_id',
        'entity_type',
        'entity_id',
    ];
}