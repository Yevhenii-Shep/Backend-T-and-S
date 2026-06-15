<?php

namespace App\Models\Concerns\Slug;

use Illuminate\Support\Str;

/** Генерация уникального slug для модели. */
trait GeneratesUniqueSlug
{
    public static function generateUniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'item';
        }

        $candidate = $slug;
        $suffix = 1;

        while (static::slugExists($candidate, $ignoreId)) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = static::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /** База для slug до сохранения (переопределить в модели при необходимости). */
    protected function slugBase(): string
    {
        if (!empty($this->name)) {
            return (string) $this->name;
        }

        if (!empty($this->email)) {
            return (string) Str::before($this->email, '@');
        }

        return static::class.'-'.($this->id ?? 'new');
    }
}