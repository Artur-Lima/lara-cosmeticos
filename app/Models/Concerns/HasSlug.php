<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Gera slug único a partir de "name" quando não informado.
 * O índice unique no banco continua sendo a garantia final.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlug(Str::slug($model->name));
            }
        });
    }

    protected function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}
