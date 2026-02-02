<?php

namespace App\Domain\Users\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramLoginToken extends Model
{
    protected $fillable = [
        'token_hash',
        'session_id',
        'user_agent',
        'ip_address',
        'telegram_id',
        'telegram_username',
        'user_id',
        'confirmed_at',
        'used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
