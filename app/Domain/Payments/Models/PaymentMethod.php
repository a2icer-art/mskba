<?php

namespace App\Domain\Payments\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Payments\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentMethod extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'type',
        'label',
        'phone',
        'display_name',
        'is_active',
        'sort_order',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
