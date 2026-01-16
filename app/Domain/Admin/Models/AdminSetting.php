<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}