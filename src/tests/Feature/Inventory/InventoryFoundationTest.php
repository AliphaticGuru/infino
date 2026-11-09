<?php

use App\Foundation\Modules\ModuleRepository;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\CreateInventory;
use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Contracts\CreatesInventory;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Data\CreateInventoryData;
use App\Modules\Inventory\Database\Factories\InventoryFactory;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('inventories table has the expected schema', function () {
    expect(Schema::hasTable('inventories'))->toBeTrue()
        ->and(Schema::hasColumns('inventories', [
            'id',
            'product_id',
            'location',
            'quantity',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('inventory module is discovered and registered', function () {
    $modules = app(ModuleRepository::class)->all();
    $manifest = $modules->firstWhere('name', 'Inventory');

    expect($manifest)->not->toBeNull()
        ->and($manifest->dependsOn('Catalog'))->toBeTrue()
        ->and($manifest->dependsOn('Core'))->toBeTrue()
        ->and(app()->getProviders(InventoryServiceProvider::class))->not->toBeEmpty()
        ->and(app(CreatesInventory::class))->toBeInstanceOf(CreateInventory::class)
        ->and(app(AdjustsStock::class))->toBeInstanceOf(AdjustStock::class);
});

test('inventory factory creates a persisted inventory record', function () {
    $factory = Inventory::factory();

    expect($factory)->toBeInstanceOf(InventoryFactory::class);

    $inventory = $factory->create();

    expect($inventory->exists)->toBeTrue()
        ->and($inventory->product_id)->toBeInt()
        ->and($inventory->location)->toBe('default')
        ->and($inventory->quantity)->toBeGreaterThanOrEqual(0)
        ->and($inventory->product)->toBeInstanceOf(Product::class);

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'product_id' => $inventory->product_id,
        'location' => 'default',
        'quantity' => $inventory->quantity,
    ]);
});

test('create inventory action persists initial stock', function () {
    $product = Product::factory()->create();

    $inventory = app(CreatesInventory::class)->handle(
        new CreateInventoryData(
            productId: $product->id,
            location: 'warehouse-a',
            quantity: 25,
        ),
    );

    expect($inventory)->toBeInstanceOf(Inventory::class)
        ->and($inventory->product_id)->toBe($product->id)
        ->and($inventory->location)->toBe('warehouse-a')
        ->and($inventory->quantity)->toBe(25)
        ->and($inventory->product->is($product))->toBeTrue();

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'product_id' => $product->id,
        'location' => 'warehouse-a',
        'quantity' => 25,
    ]);
});

test('create inventory action applies default location and quantity', function () {
    $product = Product::factory()->create();

    $inventory = app(CreatesInventory::class)->handle(
        new CreateInventoryData(productId: $product->id),
    );

    expect($inventory->location)->toBe('default')
        ->and($inventory->quantity)->toBe(0);
});

test('a product may have inventory records for multiple locations', function () {
    $product = Product::factory()->create();
    $action = app(CreatesInventory::class);

    $defaultInventory = $action->handle(
        new CreateInventoryData(productId: $product->id, quantity: 10),
    );

    $secondaryInventory = $action->handle(
        new CreateInventoryData(
            productId: $product->id,
            location: 'warehouse-b',
            quantity: 5,
        ),
    );

    expect($defaultInventory->id)->not->toBe($secondaryInventory->id)
        ->and($defaultInventory->location)->toBe('default')
        ->and($secondaryInventory->location)->toBe('warehouse-b')
        ->and(Inventory::where('product_id', $product->id)->count())->toBe(2);
});

test('create inventory action rejects a duplicate product location', function () {
    $product = Product::factory()->create();
    $action = app(CreatesInventory::class);

    $action->handle(new CreateInventoryData(productId: $product->id));

    expect(fn () => $action->handle(
        new CreateInventoryData(productId: $product->id),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 1);
});

test('create inventory action rejects negative initial quantities', function () {
    $product = Product::factory()->create();

    expect(fn () => app(CreatesInventory::class)->handle(
        new CreateInventoryData(productId: $product->id, quantity: -1),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 0);
});

test('create inventory action rejects unknown products', function () {
    expect(fn () => app(CreatesInventory::class)->handle(
        new CreateInventoryData(productId: 999999, quantity: 1),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 0);
});

test('stock adjustment increases quantity', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 15),
    );

    expect($updated->quantity)->toBe(25);

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'quantity' => 25,
    ]);
});

test('stock adjustment decreases quantity', function () {
    $inventory = Inventory::factory()->create(['quantity' => 20]);

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -8),
    );

    expect($updated->quantity)->toBe(12);

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'quantity' => 12,
    ]);
});

test('stock adjustment allows quantity to reach zero', function () {
    $inventory = Inventory::factory()->create(['quantity' => 4]);

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -4),
    );

    expect($updated->quantity)->toBe(0);
});

test('stock adjustment rejects adjustments that would make stock negative', function () {
    $inventory = Inventory::factory()->create(['quantity' => 3]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -4),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(3);
});

test('stock adjustment rejects a zero adjustment', function () {
    $inventory = Inventory::factory()->create(['quantity' => 5]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 0),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(5);
});

test('stock adjustments are isolated per location', function () {
    $product = Product::factory()->create();

    $defaultInventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'default', 'quantity' => 10]);

    $secondaryInventory = Inventory::factory()
        ->for($product)
        ->create(['location' => 'warehouse-b', 'quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $defaultInventory,
        new AdjustStockData(quantityDelta: -5),
    );

    expect($defaultInventory->fresh()->quantity)->toBe(5)
        ->and($secondaryInventory->fresh()->quantity)->toBe(10);
});

test('deleting a product removes its inventory records', function () {
    $product = Product::factory()->create();
    $inventory = Inventory::factory()->for($product)->create();

    $product->delete();

    $this->assertDatabaseMissing('inventories', [
        'id' => $inventory->id,
    ]);
});
