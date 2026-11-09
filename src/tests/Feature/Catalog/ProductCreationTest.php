<?php

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;

test('product factory creates a persisted product', function () {
    $product = Product::factory()->create();

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

test('create product action generates unique slugs for duplicate names', function () {
    $action = app(CreatesProducts::class);
    $data = new CreateProductData(
        name: 'Wireless Headphones',
        description: 'Noise cancelling over-ear headphones.',
    );

    $firstProduct = $action->handle($data);
    $secondProduct = $action->handle($data);

    expect($secondProduct->slug)
        ->toBe('wireless-headphones-2')
        ->not->toBe($firstProduct->slug);

    $this->assertDatabaseCount('products', 2);
});
