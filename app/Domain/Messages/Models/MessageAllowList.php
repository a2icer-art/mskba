<?php

namespace App\Domain\Messages\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAllowList extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'allowed_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function allowedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allowed_user_id');
    }
}
