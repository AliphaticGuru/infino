<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}