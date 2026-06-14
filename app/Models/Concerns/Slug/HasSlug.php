<?php

namespace App\Models\Concerns\Slug;

/**
 * Slug для URL (variant C: id или slug) + автогенерация при create, если slug пустой.
 */
trait HasSlug
{
    use GeneratesUniqueSlug, ResolvesIdOrSlugRouteBinding;

    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (!empty($model->slug)) {
                return;
            }

            $model->slug = static::generateUniqueSlug($model->slugBase());
        });
    }
}
