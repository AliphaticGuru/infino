<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function isDescendantOf(self $category): bool
    {
        $parent = $this->parent;

        while ($parent !== null) {
            if ($parent->is($category)) {
                return true;
            }

            $parent = $parent->parent;
        }

        return false;
    }
}
