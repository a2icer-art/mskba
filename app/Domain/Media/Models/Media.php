<?php

namespace App\Domain\Media\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'title',
        'description',
        'is_avatar',
        'is_featured',
        'collection',
        'type',
        'disk',
        'path',
        'meta',
        'size',
        'mime',
        'created_by',
        'processed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'processed_at' => 'datetime',
            'is_avatar' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
