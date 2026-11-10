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
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = self::filteredQuery($filters);

        $query->orderByDesc('created_at')->orderByDesc('id');

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Get movement history for a specific product.
     *
     * @param  array{reason?: string|null, start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function forProduct(int $productId, array $filters = []): LengthAwarePaginator
    {
        return self::paginate([...$filters, 'product_id' => $productId]);
    }

    /**
     * Get daily movement summaries grouped by date.
     *
     * @return Collection<string, array{date: string, increases: int, decreases: int}>
     */
    public static function summaries(?int $productId = null, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $filters = [
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

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
     * Count total stock movements matching the filters.
     *
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     */
    public static function countMovements(array $filters = []): int
    {
        return self::filteredQuery($filters)->count();
    }

    /**
     * Sum of positive quantity deltas across matching movements.
     *
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
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
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     */
    public static function totalDecreased(array $filters = []): int
    {
        return (int) self::filteredQuery($filters)
            ->where('quantity_delta', '<', 0)
            ->sum('quantity_delta');
    }

    /**
     * Build the shared read-only query for the supplied filters.
     *
     * @param  array{product_id?: int|null, reason?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     * @return Builder<StockMovement>
     */
    private static function filteredQuery(array $filters): Builder
    {
        $query = StockMovement::query();

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
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
