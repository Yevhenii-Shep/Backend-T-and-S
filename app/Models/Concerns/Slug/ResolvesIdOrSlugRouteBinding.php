<?php

namespace App\Models\Concerns\Slug;

use Illuminate\Database\Eloquent\Model;

/** Route binding: /api/{resource}/{id} или /api/{resource}/{slug}. */
trait ResolvesIdOrSlugRouteBinding
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return static::query()->where($field, $value)->firstOrFail();
        }

        if (ctype_digit((string) $value)) {
            $byId = static::query()->where('id', $value)->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        return static::query()->where('slug', $value)->firstOrFail();
    }
}
