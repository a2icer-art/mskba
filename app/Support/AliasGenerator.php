<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AliasGenerator
{
    public function generateBase(string $name, string $fallback = 'item'): string
    {
        $base = Str::slug($name);
        return $base ?: $fallback;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function generateUnique(string $name, string $modelClass, string $column = 'alias', string $fallback = 'item'): string
    {
        $base = $this->generateBase($name, $fallback);
        $alias = $base;
        $counter = 2;

        while ($modelClass::query()->where($column, $alias)->exists()) {
            $alias = $base . '-' . $counter;
            $counter++;
        }

        return $alias;
    }
}
