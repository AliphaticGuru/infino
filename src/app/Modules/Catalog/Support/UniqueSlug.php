<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /**
     * @param class-string<Model> $model
     */
    public static function generate(
        string $model,
        string $name,
        string $fallback,
        ?int $ignoreId = null,
    ): string {
        $baseSlug = Str::slug($name) ?: $fallback;
        $slug = $baseSlug;
        $suffix = 1;

        while ($model::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $suffix++;
            $slug = "{$baseSlug}-{$suffix}";
        }

        return $slug;
    }
}
