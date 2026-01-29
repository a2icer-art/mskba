<?php

namespace App\Domain\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class PageMeta extends Model
{
    protected $table = 'page_meta';

    protected $fillable = [
        'page_type',
        'page_id',
        'title',
        'description',
        'keywords',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
