<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Database\Factories\StockMovementFactory;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('stock movements table has the expected append-only schema', function () {
    expect(Schema::hasTable('stock_movements'))->toBeTrue()
        ->and(Schema::hasColumns('stock_movements', [
            'id',
            'product_id',
            'location',
            'quantity_delta',
            'resulting_quantity',
            'reason',
            'reference',
            'created_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('stock_movements', 'updated_at'))->toBeFalse();
});

test('stock movement factory creates a persisted movement', function () {
    $factory = StockMovement::factory();

    expect($factory)->toBeInstanceOf(StockMovementFactory::class);

    $movement = $factory->forLocation('warehouse-c')->create();

    expect($movement->exists)->toBeTrue()
        ->and($movement->product_id)->toBeInt()
        ->and($movement->product)->toBeInstanceOf(Product::class)
        ->and($movement->location)->toBe('warehouse-c')
        ->and($movement->quantity_delta)->not->toBe(0)
        ->and($movement->resulting_quantity)->toBeGreaterThanOrEqual(0)
        ->and($movement->reason)->not->toBeEmpty()
        ->and($movement->created_at)->not->toBeNull();

    $this->assertDatabaseHas('stock_movements', [
        'id' => $movement->id,
        'product_id' => $movement->product_id,
        'location' => 'warehouse-c',
        'quantity_delta' => $movement->quantity_delta,
        'resulting_quantity' => $movement->resulting_quantity,
    ]);
});

test('successful stock increase creates a complete movement record', function () {
    $product = Product::factory()->create();
    $inventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'warehouse-a', 'quantity' => 10]);

    $recordedAfter = now()->subSecond();

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(
            quantityDelta: 15,
            reason: 'Stock received',
            reference: 'PO-1001',
        ),
    );

    $movement = StockMovement::sole();

    expect($updated->quantity)->toBe(25)
        ->and($movement->product_id)->toBe($product->id)
        ->and($movement->product->is($product))->toBeTrue()
        ->and($movement->location)->toBe('warehouse-a')
        ->and($movement->quantity_delta)->toBe(15)
        ->and($movement->resulting_quantity)->toBe(25)
        ->and($movement->reason)->toBe('Stock received')
        ->and($movement->reference)->toBe('PO-1001')
        ->and($movement->created_at)->not->toBeNull()
        ->and($movement->created_at->greaterThanOrEqualTo($recordedAfter))->toBeTrue();
});

test('successful stock decrease creates a movement with a signed delta', function () {
    $product = Product::factory()->create();
    $inventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'warehouse-a', 'quantity' => 20]);

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(
            quantityDelta: -8,
            reason: 'Customer order',
            reference: 'ORDER-2002',
        ),
    );

    $movement = StockMovement::sole();

    expect($updated->quantity)->toBe(12)
        ->and($movement->quantity_delta)->toBe(-8)
        ->and($movement->resulting_quantity)->toBe(12)
        ->and($movement->location)->toBe('warehouse-a')
        ->and($movement->product_id)->toBe($product->id)
        ->and($movement->reference)->toBe('ORDER-2002');
});

test('a movement may omit the optional reference', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Manual correction'),
    );

    $movement = StockMovement::sole();

    expect($movement->reference)->toBeNull()
        ->and($movement->reason)->toBe('Manual correction');
});

test('multiple adjustments create separate historical records', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);
    $action = app(AdjustsStock::class);

    $action->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    $firstMovement = StockMovement::query()->orderBy('id')->firstOrFail();
    $firstSnapshot = $firstMovement->getAttributes();

    $action->handle(
        $inventory->fresh(),
        new AdjustStockData(quantityDelta: -3, reason: 'Customer order'),
    );

    $secondMovement = StockMovement::query()->orderByDesc('id')->firstOrFail();

    expect(StockMovement::count())->toBe(2)
        ->and($firstMovement->fresh()->getAttributes())->toBe($firstSnapshot)
        ->and($firstMovement->quantity_delta)->toBe(5)
        ->and($firstMovement->resulting_quantity)->toBe(15)
        ->and($secondMovement->quantity_delta)->toBe(-3)
        ->and($secondMovement->resulting_quantity)->toBe(12);
});

test('movement records remain unchanged after subsequent adjustments', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);
    $action = app(AdjustsStock::class);

    $action->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 2, reason: 'Stock received', reference: 'FIRST'),
    );

    $movement = StockMovement::sole();
    $original = $movement->getAttributes();

    $action->handle(
        $inventory->fresh(),
        new AdjustStockData(quantityDelta: 7, reason: 'Stock received', reference: 'SECOND'),
    );

    $movement->refresh();

    expect($movement->getAttributes())->toBe($original);
});

test('a rejected negative adjustment produces no movement', function () {
    $inventory = Inventory::factory()->create(['quantity' => 3]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -4, reason: 'Stock write-off'),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(3)
        ->and(StockMovement::count())->toBe(0);
});

test('a zero adjustment produces no movement', function () {
    $inventory = Inventory::factory()->create(['quantity' => 5]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 0, reason: 'No change'),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(5)
        ->and(StockMovement::count())->toBe(0);
});

test('invalid reason and reference values produce no movement', function () {
    $inventory = Inventory::factory()->create(['quantity' => 5]);
    $action = app(AdjustsStock::class);

    expect(fn () => $action->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 1, reason: ''),
    ))->toThrow(ValidationException::class);

    expect(fn () => $action->handle(
        $inventory,
        new AdjustStockData(
            quantityDelta: 1,
            reason: 'Stock received',
            reference: str_repeat('a', 256),
        ),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(5)
        ->and(StockMovement::count())->toBe(0);
});

test('movement persistence failure rolls back the stock update', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);

    StockMovement::creating(function (): void {
        throw new RuntimeException('Forced movement persistence failure.');
    });

    try {
        expect(fn () => app(AdjustsStock::class)->handle(
            $inventory,
            new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
        ))->toThrow(RuntimeException::class);
    } finally {
        StockMovement::flushEventListeners();
    }

    expect($inventory->fresh()->quantity)->toBe(10)
        ->and(StockMovement::count())->toBe(0);
});

test('an outer transaction rollback reverts both stock and movement', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);

    try {
        DB::transaction(function () use ($inventory): void {
            app(AdjustsStock::class)->handle(
                $inventory,
                new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
            );

            throw new RuntimeException('Outer rollback.');
        });
    } catch (RuntimeException) {
        //
    }

    expect($inventory->fresh()->quantity)->toBe(10)
        ->and(StockMovement::count())->toBe(0);
});

test('movements are recorded against the adjusted location only', function () {
    $product = Product::factory()->create();

    $defaultInventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'default', 'quantity' => 10]);

    $secondaryInventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'warehouse-b', 'quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $secondaryInventory,
        new AdjustStockData(quantityDelta: -4, reason: 'Customer order'),
    );

    $movement = StockMovement::sole();

    expect($movement->location)->toBe('warehouse-b')
        ->and($movement->product_id)->toBe($product->id)
        ->and($defaultInventory->fresh()->quantity)->toBe(10)
        ->and($secondaryInventory->fresh()->quantity)->toBe(6);
});

test('deleting a product removes its stock movements', function () {
    $product = Product::factory()->create();
    $inventory = Inventory::factory()->for($product)->create(['quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    $movement = StockMovement::sole();

    $product->delete();

    $this->assertDatabaseMissing('stock_movements', [
        'id' => $movement->id,
    ]);
});
