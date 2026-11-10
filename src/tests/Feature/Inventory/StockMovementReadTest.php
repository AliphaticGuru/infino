<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockMovementReadService;

test('stock movement read service paginates all movements', function () {
    $product = Product::factory()->create();

    $inv1 = Inventory::create([
        'product_id' => $product->id,
        'location' => 'warehouse-1',
        'quantity' => 10,
    ]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 5; $i++) {
        $action->handle(
            $inv1,
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
    $product = Product::factory()->create();

    $inv = Inventory::create([
        'product_id' => $product->id,
        'location' => 'default',
        'quantity' => 10,
    ]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 10; $i++) {
        $action->handle(
            $inv,
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

    $inv1 = Inventory::create(['product_id' => $product1->id, 'location' => 'default', 'quantity' => 10]);
    $inv2 = Inventory::create(['product_id' => $product2->id, 'location' => 'default', 'quantity' => 10]);

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
        expect($movement->product_id)->toBe($product1->id);
    }
});

test('stock movement read service filters by reason', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Customer order'),
    );

    $page = StockMovementReadService::paginate(['reason' => 'Stock received']);

    expect($page->total())->toBe(1);
    foreach ($page->items() as $movement) {
        expect($movement->reason)->toBe('Stock received');
    }
});

test('stock movement read service filters by date range', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(
            quantityDelta: 5,
            reason: 'Stock received',
            reference: '2026-11-08',
        ),
    );

    app(AdjustStock::class)->handle(
        $inv,
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

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    $action = app(AdjustStock::class);
    for ($i = 1; $i <= 4; $i++) {
        $action->handle(
            $inv,
            new AdjustStockData(
                quantityDelta: $i * 3,
                reason: "Adj-$i",
            ),
        );
    }

    $page = StockMovementReadService::forProduct($product->id);

    expect($page->total())->toBe(4);
    foreach ($page->items() as $movement) {
        expect($movement->product_id)->toBe($product->id);
    }
});

test('stock movement read service summaries include increases and decreases', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    // Increases
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 10, reason: 'Stock received'),
    );

    // Decreases
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -4, reason: 'Customer order'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -8, reason: 'Stock write-off'),
    );

    $summaries = StockMovementReadService::summaries($product->id);

    expect($summaries->count())->toBeGreaterThan(0);
    foreach ($summaries as $s) {
        expect($s['increases'])->toBeGreaterThan(0);
        expect($s['decreases'])->toBeLessThan(0);
        expect($s['date'])->not->toBeNull();
    }
});

test('stock movement read service summaries distinguish increases from decreases', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 7, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -2, reason: 'Customer order'),
    );

    $summaries = StockMovementReadService::summaries($product->id);

    expect(count($summaries))->toBe(1)
        ->and($summaries->first()['increases'])->toBe(7)
        ->and($summaries->first()['decreases'])->toBe(-2);
});

test('stock movement read service empty history for unknown product', function () {
    $page = StockMovementReadService::forProduct(999999);

    expect($page->total())->toBe(0);
    expect(count($page->items()))->toBe(0);
});

test('stock movement read service empty summaries for unknown product', function () {
    $summaries = StockMovementReadService::summaries(999999);

    expect(count($summaries))->toBe(0);
});

test('stock movement read service totalIncreased and totalDecreased', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    // 3 increases totaling +15
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    // 2 decreases totaling -6
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );

    expect(StockMovementReadService::totalIncreased())->toBe(15);
    expect(StockMovementReadService::totalDecreased())->toBe(-6);
});

test('stock movement read service never modifies inventory records', function () {
    $product = Product::factory()->create();

    $inventory = Inventory::create([
        'product_id' => $product->id,
        'location' => 'default',
        'quantity' => 10,
    ]);

    app(AdjustStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    $quantity = $inventory->fresh()->quantity;
    $movementCount = StockMovement::count();

    StockMovementReadService::paginate();
    StockMovementReadService::paginate(['product_id' => $inventory->product_id]);
    StockMovementReadService::forProduct($inventory->product_id);
    StockMovementReadService::summaries($inventory->product_id);
    StockMovementReadService::totalIncreased();
    StockMovementReadService::totalDecreased();
    StockMovementReadService::countMovements();

    expect($inventory->fresh()->quantity)->toBe($quantity)
        ->and(StockMovement::count())->toBe($movementCount);
});

test('stock movement read service counts movements correctly', function () {
    $product = Product::factory()->create();

    $inv = Inventory::create(['product_id' => $product->id, 'location' => 'default', 'quantity' => 10]);

    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 5, reason: 'Adj 1'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: -2, reason: 'Adj 2'),
    );
    app(AdjustStock::class)->handle(
        $inv,
        new AdjustStockData(quantityDelta: 3, reason: 'Adj 3'),
    );

    expect(StockMovementReadService::countMovements())->toBe(3)
        ->and(StockMovementReadService::countMovements(['product_id' => $product->id]))->toBe(3);
});

test('stock movement read service works with multiple locations', function () {
    $product = Product::factory()->create();

    $inv1 = Inventory::create(['product_id' => $product->id, 'location' => 'warehouse-a', 'quantity' => 10]);
    $inv2 = Inventory::create(['product_id' => $product->id, 'location' => 'warehouse-b', 'quantity' => 10]);

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
    expect($locations)->toContain('warehouse-a');
    expect($locations)->toContain('warehouse-b');
});
