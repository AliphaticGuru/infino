<?php

use App\Foundation\Modules\ModuleRepository;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('products table has the expected schema', function () {
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasColumns('products', [
            'id',
            'name',
            'slug',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('catalog module is discovered and registered', function () {
    $modules = app(ModuleRepository::class)->all();

    expect($modules->firstWhere('name', 'Catalog'))->not->toBeNull()
        ->and(app()->getProviders(CatalogServiceProvider::class))->not->toBeEmpty()
        ->and(app(CreatesProducts::class))->toBeInstanceOf(CreateProduct::class);
});

test('product factory creates a persisted product', function () {
    $factory = Product::factory();

    expect($factory)->toBeInstanceOf(ProductFactory::class);

    $product = $factory->create();

    expect($product->exists)->toBeTrue()
        ->and($product->name)->not->toBeEmpty()
        ->and($product->slug)->not->toBeEmpty()
        ->and($product->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'slug' => $product->slug,
    ]);
});

test('create product action persists a product', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(
            name: 'Wireless Headphones',
            description: 'Noise cancelling over-ear headphones.',
        ),
    );

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('Wireless Headphones')
        ->and($product->slug)->toBe('wireless-headphones')
        ->and($product->description)->toBe('Noise cancelling over-ear headphones.')
        ->and($product->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'slug' => 'wireless-headphones',
    ]);
});

test('create product action accepts a missing description', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: 'Wireless Headphones'),
    );

    expect($product->description)->toBeNull();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'description' => null,
    ]);
});

test('create product action generates unique slugs for duplicate names', function () {
    $action = app(CreatesProducts::class);
    $data = new CreateProductData(
        name: 'Wireless Headphones',
        description: 'Noise cancelling over-ear headphones.',
    );

    $firstProduct = $action->handle($data);
    $secondProduct = $action->handle($data);
    $thirdProduct = $action->handle($data);

    expect($firstProduct->slug)->toBe('wireless-headphones')
        ->and($secondProduct->slug)->toBe('wireless-headphones-2')
        ->and($thirdProduct->slug)->toBe('wireless-headphones-3');

    $this->assertDatabaseCount('products', 3);
});

test('create product action falls back to a default slug when the name has no slug characters', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: '!!!'),
    );

    expect($product->slug)->toBe('product');
});

test('create product action requires a name', function () {
    $action = app(CreatesProducts::class);

    expect(fn () => $action->handle(new CreateProductData(name: '')))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 0);
});

test('create product action rejects names longer than 255 characters', function () {
    $action = app(CreatesProducts::class);

    expect(fn () => $action->handle(
        new CreateProductData(name: str_repeat('a', 256)),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 0);
});

test('create product action creates a default variant', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: 'Wireless Headphones'),
    );

    $variant = $product->variants()->sole();

    expect($variant)->toBeInstanceOf(ProductVariant::class)
        ->and($variant->product_id)->toBe($product->id)
        ->and($variant->sku)->toBe('SKU-'.$product->id)
        ->and($variant->label)->toBe('Default')
        ->and($variant->variant_key)->toBeNull()
        ->and($variant->is_active)->toBeTrue()
        ->and($variant->position)->toBe(0);

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'product_id' => $product->id,
        'sku' => 'SKU-'.$product->id,
    ]);
});

test('create product action accepts a custom default variant sku', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: 'Custom SKU Product', sku: 'CUSTOM-001'),
    );

    expect($product->variants()->sole()->sku)->toBe('CUSTOM-001');
});

test('create product action rejects a duplicate sku', function () {
    $action = app(CreatesProducts::class);

    $action->handle(new CreateProductData(name: 'First Product', sku: 'DUP-001'));

    expect(fn () => $action->handle(
        new CreateProductData(name: 'Second Product', sku: 'DUP-001'),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 1);
    $this->assertDatabaseCount('product_variants', 1);
});

test('create product action generates a collision-safe sku', function () {
    $action = app(CreatesProducts::class);

    $first = $action->handle(
        new CreateProductData(name: 'First Product', sku: 'SKU-2'),
    );
    $second = $action->handle(
        new CreateProductData(name: 'Second Product'),
    );

    expect($first->variants()->sole()->sku)->toBe('SKU-2')
        ->and($second->id)->toBe(2)
        ->and($second->variants()->sole()->sku)->toBe('SKU-2-2');
});

test('create product action rolls back the product when variant creation fails', function () {
    ProductVariant::creating(function (): void {
        throw new RuntimeException('Forced variant creation failure.');
    });

    try {
        expect(fn () => app(CreatesProducts::class)->handle(
            new CreateProductData(name: 'Rollback Product'),
        ))->toThrow(RuntimeException::class);
    } finally {
        ProductVariant::flushEventListeners();
    }

    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_variants', 0);
});
