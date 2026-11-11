<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only stock movement history service.
 *
 * This service provides pagination, filtering and summarisation of the
 * append-only stock_movements table. It does not modify any records.
 */
final class StockMovementReadService
{
    /**
     * Get a paginated list of stock movements with optional filtering.
     *
     * @param  array{variant_id?: int|null, product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = self::filteredQuery($filters);

        $query->orderByDesc('created_at')->orderByDesc('id');

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Get movement history for a specific product across all its variants.
     *
     * @param  array{variant_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function forProduct(int $productId, array $filters = []): LengthAwarePaginator
    {
        return self::paginate([...$filters, 'product_id' => $productId]);
    }

    /**
     * Get movement history for a specific product variant.
     *
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function forVariant(int $variantId, array $filters = []): LengthAwarePaginator
    {
        return self::paginate([...$filters, 'variant_id' => $variantId]);
    }

    /**
     * Get daily movement summaries grouped by date for a product.
     *
     * @return Collection<string, array{date: string, increases: int, decreases: int}>
     */
    public static function summaries(?int $productId = null, ?string $startDate = null, ?string $endDate = null): Collection
    {
        return self::summarize([
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Get daily movement summaries grouped by date for a product variant.
     *
     * @return Collection<string, array{date: string, increases: int, decreases: int}>
     */
    public static function summariesForVariant(?int $variantId = null, ?string $startDate = null, ?string $endDate = null): Collection
    {
        return self::summarize([
            'variant_id' => $variantId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Count total stock movements matching the filters.
     *
     * @param  array{variant_id?: int|null, product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     */
    public static function countMovements(array $filters = []): int
    {
        return self::filteredQuery($filters)->count();
    }

    /**
     * Sum of positive quantity deltas across matching movements.
     *
     * @param  array{variant_id?: int|null, product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     */
    public static function totalIncreased(array $filters = []): int
    {
        return (int) self::filteredQuery($filters)
            ->where('quantity_delta', '>', 0)
            ->sum('quantity_delta');
    }

    /**
     * Sum of negative quantity deltas across matching movements.
     *
     * @param  array{variant_id?: int|null, product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     */
    public static function totalDecreased(array $filters = []): int
    {
        return (int) self::filteredQuery($filters)
            ->where('quantity_delta', '<', 0)
            ->sum('quantity_delta');
    }

    /**
     * @param  array{product_id?: int|null, variant_id?: int|null, start_date?: string|null, end_date?: string|null}  $filters
     * @return Collection<string, array{date: string, increases: int, decreases: int}>
     */
    private static function summarize(array $filters): Collection
    {
        return self::filteredQuery($filters)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (StockMovement $movement): string => $movement->created_at?->format('Y-m-d') ?? '')
            ->map(fn (Collection $dailyMovements, string $date): array => [
                'date' => $date,
                'increases' => (int) $dailyMovements
                    ->where('quantity_delta', '>', 0)
                    ->sum('quantity_delta'),
                'decreases' => (int) $dailyMovements
                    ->where('quantity_delta', '<', 0)
                    ->sum('quantity_delta'),
            ]);
    }

    /**
     * Build the shared read-only query for the supplied filters.
     *
     * @param  array{variant_id?: int|null, product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     * @return Builder<StockMovement>
     */
    private static function filteredQuery(array $filters): Builder
    {
        $query = StockMovement::query();

        if (! empty($filters['variant_id'])) {
            $query->where('variant_id', $filters['variant_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas(
                'variant',
                fn (Builder $variantQuery) => $variantQuery->where('product_id', $filters['product_id']),
            );
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', 'like', "%{$filters['reason']}%");
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        return $query;
    }
}
