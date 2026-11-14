<?php

declare(strict_types=1);

use App\Foundation\Modules\ModuleRepository;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Filament\AdminPanelProvider;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\CreateInventory;
use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Contracts\CreatesInventory;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Data\CreateInventoryData;
use App\Modules\Inventory\Database\Factories\InventoryFactory;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Pricing\Actions\SetVariantPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('inventories table has the expected schema', function () {
    expect(Schema::hasTable('inventories'))->toBeTrue()
        ->and(Schema::hasColumns('inventories', [
            'id',
            'variant_id',
            'location',
            'quantity',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('inventories', 'product_id'))->toBeFalse();
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
        ->and($inventory->variant_id)->toBeInt()
        ->and($inventory->location)->toBe('default')
        ->and($inventory->quantity)->toBeGreaterThanOrEqual(0)
        ->and($inventory->variant)->toBeInstanceOf(ProductVariant::class)
        ->and($inventory->variant->product)->toBeInstanceOf(Product::class);

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'variant_id' => $inventory->variant_id,
        'location' => 'default',
        'quantity' => $inventory->quantity,
    ]);
});

test('create inventory action persists initial stock', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = app(CreatesInventory::class)->handle(
        new CreateInventoryData(
            variantId: $variant->id,
            location: 'warehouse-a',
            quantity: 25,
        ),
    );

    expect($inventory)->toBeInstanceOf(Inventory::class)
        ->and($inventory->variant_id)->toBe($variant->id)
        ->and($inventory->location)->toBe('warehouse-a')
        ->and($inventory->quantity)->toBe(25)
        ->and($inventory->variant->is($variant))->toBeTrue();

    $this->assertDatabaseHas('inventories', [
        'id' => $inventory->id,
        'variant_id' => $variant->id,
        'location' => 'warehouse-a',
        'quantity' => 25,
    ]);
});

test('create inventory action applies default location and quantity', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $inventory = app(CreatesInventory::class)->handle(
        new CreateInventoryData(variantId: $variant->id),
    );

    expect($inventory->location)->toBe('default')
        ->and($inventory->quantity)->toBe(0);
});

test('a variant may have inventory records for multiple locations', function () {
    $variant = Product::factory()->create()->variants()->sole();
    $action = app(CreatesInventory::class);

    $defaultInventory = $action->handle(
        new CreateInventoryData(variantId: $variant->id, quantity: 10),
    );

    $secondaryInventory = $action->handle(
        new CreateInventoryData(
            variantId: $variant->id,
            location: 'warehouse-b',
            quantity: 5,
        ),
    );

    expect($defaultInventory->id)->not->toBe($secondaryInventory->id)
        ->and($defaultInventory->location)->toBe('default')
        ->and($secondaryInventory->location)->toBe('warehouse-b')
        ->and(Inventory::where('variant_id', $variant->id)->count())->toBe(2);
});

test('create inventory action rejects a duplicate variant location', function () {
    $variant = Product::factory()->create()->variants()->sole();
    $action = app(CreatesInventory::class);

    $action->handle(new CreateInventoryData(variantId: $variant->id));

    expect(fn () => $action->handle(
        new CreateInventoryData(variantId: $variant->id),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 1);
});

test('create inventory action rejects negative initial quantities', function () {
    $variant = Product::factory()->create()->variants()->sole();

    expect(fn () => app(CreatesInventory::class)->handle(
        new CreateInventoryData(variantId: $variant->id, quantity: -1),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 0);
});

test('create inventory action rejects unknown variants', function () {
    expect(fn () => app(CreatesInventory::class)->handle(
        new CreateInventoryData(variantId: 999999, quantity: 1),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('inventories', 0);
});

test('stock adjustment increases quantity', function () {
    $inventory = Inventory::factory()->create(['quantity' => 10]);

    $updated = app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 15, reason: 'Stock received'),
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
        new AdjustStockData(quantityDelta: -8, reason: 'Customer order'),
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
        new AdjustStockData(quantityDelta: -4, reason: 'Stock write-off'),
    );

    expect($updated->quantity)->toBe(0);
});

test('stock adjustment rejects adjustments that would make stock negative', function () {
    $inventory = Inventory::factory()->create(['quantity' => 3]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: -4, reason: 'Stock write-off'),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(3);
});

test('stock adjustment rejects a zero adjustment', function () {
    $inventory = Inventory::factory()->create(['quantity' => 5]);

    expect(fn () => app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 0, reason: 'No change'),
    ))->toThrow(ValidationException::class);

    expect($inventory->fresh()->quantity)->toBe(5);
});

test('stock adjustments are isolated per location', function () {
    $variant = Product::factory()->create()->variants()->sole();

    $defaultInventory = Inventory::factory()
        ->forVariant($variant)
        ->create(['location' => 'default', 'quantity' => 10]);

    $secondaryInventory = Inventory::factory()
        ->forVariant($variant)
        ->create(['location' => 'warehouse-b', 'quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $defaultInventory,
        new AdjustStockData(quantityDelta: -5, reason: 'Customer order'),
    );

    expect($defaultInventory->fresh()->quantity)->toBe(5)
        ->and($secondaryInventory->fresh()->quantity)->toBe(10);
});

test('stock is isolated per variant of the same product', function () {
    $product = Product::factory()->withoutVariant()->create();
    $firstVariant = ProductVariant::factory()->forProduct($product)->create();
    $secondVariant = ProductVariant::factory()->forProduct($product)->create();

    $firstInventory = Inventory::factory()
        ->forVariant($firstVariant)
        ->create(['quantity' => 10]);

    $secondInventory = Inventory::factory()
        ->forVariant($secondVariant)
        ->create(['quantity' => 10]);

    app(AdjustsStock::class)->handle(
        $firstInventory,
        new AdjustStockData(quantityDelta: -4, reason: 'Customer order'),
    );

    expect($firstInventory->fresh()->quantity)->toBe(6)
        ->and($secondInventory->fresh()->quantity)->toBe(10);
});

test('deleting a product removes its inventory records', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();
    $inventory = Inventory::factory()->forVariant($variant)->create();

    $product->delete();

    $this->assertDatabaseMissing('inventories', [
        'id' => $inventory->id,
    ]);
});

test('deleting a variant removes its inventory records', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();
    $inventory = Inventory::factory()->forVariant($variant)->create();

    $variant->delete();

    $this->assertDatabaseMissing('inventories', [
        'id' => $inventory->id,
    ]);
});
