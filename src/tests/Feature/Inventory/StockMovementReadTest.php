<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockMovementReadService;

test('stock movement read service paginates all movements', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()
        ->forVariant($variant)
        ->create(['location' => 'warehouse-1', 'quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 5; $i++) {
        $action->handle(
            $inventory,
            new AdjustStockData(
                quantityDelta: $i * 3,
                reason: "Adjustment #$i",
            ),
        );
    }

    $page = StockMovementReadService::paginate();

    expect($page->total())->toBe(5)
        ->and($page->perPage())->toBe(20)
        ->and($page->currentPage())->toBe(1)
        ->and($page->hasMorePages())->toBeFalse()
        ->and(count($page->items()))->toBe(5);
});

test('stock movement read service paginates with custom perPage', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()
        ->forVariant($variant)
        ->create(['location' => 'default', 'quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 10; $i++) {
        $action->handle(
            $inventory,
            new AdjustStockData(
                quantityDelta: $i * 3,
                reason: "Adjustment #$i",
            ),
        );
    }

    $page = StockMovementReadService::paginate(['per_page' => 3]);

    expect($page->total())->toBe(10)
        ->and($page->perPage())->toBe(3)
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(4)
        ->and(count($page->items()))->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

test('stock movement read service filters by product', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $variant1 = $product1->variants()->sole();
    $variant2 = $product2->variants()->sole();

    $inv1 = Inventory::factory()->forVariant($variant1)->create(['quantity' => 10]);
    $inv2 = Inventory::factory()->forVariant($variant2)->create(['quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 3; $i++) {
        $action->handle(
            $inv1,
            new AdjustStockData(quantityDelta: $i * 3, reason: 'Product1 adjustment'),
        );
    }

    for ($i = 1; $i <= 2; $i++) {
        $action->handle(
            $inv2,
            new AdjustStockData(quantityDelta: $i * 3, reason: 'Product2 adjustment'),
        );
    }

    $page = StockMovementReadService::paginate(['product_id' => $product1->id]);

    expect($page->total())->toBe(3);
    foreach ($page->items() as $movement) {
        expect($movement->variant->product_id)->toBe($product1->id);
    }
});

test('stock movement read service filters by variant', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $variant1 = $product1->variants()->sole();
    $variant2 = $product2->variants()->sole();

    $inv1 = Inventory::factory()->forVariant($variant1)->create(['quantity' => 10]);
    $inv2 = Inventory::factory()->forVariant($variant2)->create(['quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 3; $i++) {
        $action->handle(
            $inv1,
            new AdjustStockData(quantityDelta: $i * 3, reason: 'Variant1 adjustment'),
        );
    }

    for ($i = 1; $i <= 2; $i++) {
        $action->handle(
            $inv2,
            new AdjustStockData(quantityDelta: $i * 3, reason: 'Variant2 adjustment'),
        );
    }

    $page = StockMovementReadService::paginate(['variant_id' => $variant1->id]);

    expect($page->total())->toBe(3);
    foreach ($page->items() as $movement) {
        expect($movement->variant_id)->toBe($variant1->id);
    }
});

test('stock movement read service filters by reason', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Customer order'),
    );

    $page = StockMovementReadService::paginate(['reason' => 'Stock received']);

    expect($page->total())->toBe(1);
    foreach ($page->items() as $movement) {
        expect($movement->reason)->toBe('Stock received');
    }
});

test('stock movement read service filters by date range', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(
            quantityDelta: 5,
            reason: 'Stock received',
            reference: '2026-11-08',
        ),
    );

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(
            quantityDelta: 5,
            reason: 'Stock received',
            reference: '2026-11-09',
        ),
    );

    $page = StockMovementReadService::paginate([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($page->total())->toBe(2);
});

test('stock movement read service returns product-specific history', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 4; $i++) {
        $action->handle(
            $inventory,
            new AdjustStockData(
                quantityDelta: $i * 3,
                reason: "Adj-$i",
            ),
        );
    }

    $page = StockMovementReadService::forProduct($product->id);

    expect($page->total())->toBe(4);
    foreach ($page->items() as $movement) {
        expect($movement->variant->product_id)->toBe($product->id);
    }
});

test('stock movement read service returns variant-specific history', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 4; $i++) {
        $action->handle(
            $inventory,
            new AdjustStockData(
                quantityDelta: $i * 3,
                reason: "Adj-$i",
            ),
        );
    }

    $page = StockMovementReadService::forVariant($variant->id);

    expect($page->total())->toBe(4);
    foreach ($page->items() as $movement) {
        expect($movement->variant_id)->toBe($variant->id);
    }
});

test('stock movement read service summaries include increases and decreases', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 10, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -4, reason: 'Customer order'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -8, reason: 'Stock write-off'),
    );

    $summaries = StockMovementReadService::summaries($product->id);

    expect($summaries->count())->toBeGreaterThan(0);
    foreach ($summaries as $summary) {
        expect($summary['increases'])->toBeGreaterThan(0)
            ->and($summary['decreases'])->toBeLessThan(0)
            ->and($summary['date'])->not->toBeNull();
    }
});

test('stock movement read service summaries distinguish increases from decreases', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 7, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -2, reason: 'Customer order'),
    );

    $summaries = StockMovementReadService::summaries($product->id);

    expect(count($summaries))->toBe(1)
        ->and($summaries->first()['increases'])->toBe(7)
        ->and($summaries->first()['decreases'])->toBe(-2);
});

test('stock movement read service summaries for a variant', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 9, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );

    $summaries = StockMovementReadService::summariesForVariant($variant->id);

    expect(count($summaries))->toBe(1)
        ->and($summaries->first()['increases'])->toBe(9)
        ->and($summaries->first()['decreases'])->toBe(-3);
});

test('stock movement read service empty history for unknown product', function () {
    $page = StockMovementReadService::forProduct(999999);

    expect($page->total())->toBe(0)
        ->and(count($page->items()))->toBe(0);
});

test('stock movement read service empty history for unknown variant', function () {
    $page = StockMovementReadService::forVariant(999999);

    expect($page->total())->toBe(0)
        ->and(count($page->items()))->toBe(0);
});

test('stock movement read service empty summaries for unknown product and variant', function () {
    expect(count(StockMovementReadService::summaries(999999)))->toBe(0)
        ->and(count(StockMovementReadService::summariesForVariant(999999)))->toBe(0);
});

test('stock movement read service totalIncreased and totalDecreased', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );

    expect(StockMovementReadService::totalIncreased())->toBe(15)
        ->and(StockMovementReadService::totalDecreased())->toBe(-6);
});

test('stock movement read service never modifies inventory records', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = Inventory::factory()
        ->forVariant($variant)
        ->create(['location' => 'default', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    $quantity = $inventory->fresh()->quantity;
    $movementCount = StockMovement::count();

    StockMovementReadService::paginate();
    StockMovementReadService::paginate(['product_id' => $variant->product_id]);
    StockMovementReadService::paginate(['variant_id' => $variant->id]);
    StockMovementReadService::forProduct($variant->product_id);
    StockMovementReadService::forVariant($variant->id);
    StockMovementReadService::summaries($variant->product_id);
    StockMovementReadService::summariesForVariant($variant->id);
    StockMovementReadService::totalIncreased();
    StockMovementReadService::totalDecreased();
    StockMovementReadService::countMovements();

    expect($inventory->fresh()->quantity)->toBe($quantity)
        ->and(StockMovement::count())->toBe($movementCount);
});

test('stock movement read service counts movements correctly', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Adj 1'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -2, reason: 'Adj 2'),
    );
    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 3, reason: 'Adj 3'),
    );

    expect(StockMovementReadService::countMovements())->toBe(3)
        ->and(StockMovementReadService::countMovements(['product_id' => $product->id]))->toBe(3)
        ->and(StockMovementReadService::countMovements(['variant_id' => $variant->id]))->toBe(3);
});

test('stock movement read service works with multiple locations', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $inv1 = Inventory::factory()->forVariant($variant)->create(['location' => 'warehouse-a', 'quantity' => 10]);
    $inv2 = Inventory::factory()->forVariant($variant)->create(['location' => 'warehouse-b', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inv1,
        new AdjustStockData(quantityDelta: 3, reason: 'Stock received'),
    );

    app(AdjustStock::class)->handle(
        $inv2,
        new AdjustStockData(quantityDelta: -2, reason: 'Customer order'),
    );

    $page = StockMovementReadService::paginate(['product_id' => $product->id]);

    expect($page->total())->toBe(2);
    $locations = array_column($page->items(), 'location');
    expect($locations)->toContain('warehouse-a')
        ->and($locations)->toContain('warehouse-b');
});

test('product history spans multiple variants while variant history stays isolated', function () {
    $product = Product::factory()->withoutVariant()->create();
    $firstVariant = ProductVariant::factory()->forProduct($product)->create();
    $secondVariant = ProductVariant::factory()->forProduct($product)->create();

    $firstInventory = Inventory::factory()->forVariant($firstVariant)->create(['quantity' => 10]);
    $secondInventory = Inventory::factory()->forVariant($secondVariant)->create(['quantity' => 10]);

    app(AdjustStock::class)->handle(
        $firstInventory,
        new AdjustStockData(quantityDelta: 3, reason: 'First variant'),
    );

    app(AdjustStock::class)->handle(
        $secondInventory,
        new AdjustStockData(quantityDelta: 4, reason: 'Second variant'),
    );

    expect(StockMovementReadService::forProduct($product->id)->total())->toBe(2)
        ->and(StockMovementReadService::forVariant($firstVariant->id)->total())->toBe(1)
        ->and(StockMovementReadService::forVariant($secondVariant->id)->total())->toBe(1);
});
