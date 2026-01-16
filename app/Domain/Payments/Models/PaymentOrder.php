<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'label',
    ];
}