<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Database\Factories\StockMovementFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $location
 * @property int $quantity_delta
 * @property int $resulting_quantity
 * @property string $reason
 * @property string|null $reference
 * @property CarbonInterface|null $created_at
 * @property-read Product $product
 */
final class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * Stock movements are append-only and have no updated timestamp.
     */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'quantity_delta' => 'integer',
            'resulting_quantity' => 'integer',
        ];
    }

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
