<?php

namespace App\Domain\Messages\Models;

use App\Domain\Messages\Enums\MessagePrivacyMode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagePrivacySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'mode' => MessagePrivacyMode::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
