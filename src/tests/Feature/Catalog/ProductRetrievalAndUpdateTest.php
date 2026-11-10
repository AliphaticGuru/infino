<?php

use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Data\UpdateProductData;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('product retrieval and update contracts resolve through the catalog module', function () {
    expect(app(FindsProducts::class))->toBeInstanceOf(FindProduct::class)
        ->and(app(UpdatesProducts::class))->toBeInstanceOf(UpdateProduct::class);
});

test('product can be retrieved by id', function () {
    $product = Product::factory()->create([
        'name' => 'Wireless Headphones',
        'slug' => 'wireless-headphones',
    ]);

    $found = app(FindsProducts::class)->find($product->id);

    expect($found->is($product))->toBeTrue()
        ->and($found->name)->toBe('Wireless Headphones')
        ->and($found->slug)->toBe('wireless-headphones');
});

test('product can be retrieved by slug', function () {
    $product = Product::factory()->create([
        'name' => 'Wireless Headphones',
        'slug' => 'wireless-headphones',
    ]);

    $found = app(FindsProducts::class)->findBySlug('wireless-headphones');

    expect($found->is($product))->toBeTrue()
        ->and($found->id)->toBe($product->id);
});

test('retrieving an unknown product id fails', function () {
    expect(fn () => app(FindsProducts::class)->find(999999))
        ->toThrow(ModelNotFoundException::class);
});

test('retrieving an unknown product slug fails', function () {
    expect(fn () => app(FindsProducts::class)->findBySlug('missing-product'))
        ->toThrow(ModelNotFoundException::class);
});

test('update product action persists name, description and active status', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'description' => 'Old description.',
        'is_active' => true,
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(
            name: 'New Name',
            description: 'Updated description.',
            isActive: false,
        ),
    );

    expect($updated->name)->toBe('New Name')
        ->and($updated->slug)->toBe('new-name')
        ->and($updated->description)->toBe('Updated description.')
        ->and($updated->is_active)->toBeFalse();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'New Name',
        'slug' => 'new-name',
        'description' => 'Updated description.',
        'is_active' => false,
    ]);
});

test('update product action preserves fields that were not supplied', function () {
    $product = Product::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'stable-name',
        'description' => 'Existing description.',
        'is_active' => false,
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(description: 'Changed description.'),
    );

    expect($updated->name)->toBe('Stable Name')
        ->and($updated->slug)->toBe('stable-name')
        ->and($updated->description)->toBe('Changed description.')
        ->and($updated->is_active)->toBeFalse();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Stable Name',
        'slug' => 'stable-name',
        'description' => 'Changed description.',
        'is_active' => false,
    ]);
});

test('update product action can change only the active status', function () {
    $product = Product::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'stable-name',
        'description' => 'Existing description.',
        'is_active' => true,
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(isActive: false),
    );

    expect($updated->name)->toBe('Stable Name')
        ->and($updated->slug)->toBe('stable-name')
        ->and($updated->description)->toBe('Existing description.')
        ->and($updated->is_active)->toBeFalse();
});

test('update product action without supplied fields keeps the product unchanged', function () {
    $product = Product::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'stable-name',
        'description' => 'Existing description.',
        'is_active' => true,
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData,
    );

    expect($updated->name)->toBe('Stable Name')
        ->and($updated->slug)->toBe('stable-name')
        ->and($updated->description)->toBe('Existing description.')
        ->and($updated->is_active)->toBeTrue();
});

test('update product action regenerates the slug when the name changes', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: 'Brand New Name'),
    );

    expect($updated->name)->toBe('Brand New Name')
        ->and($updated->slug)->toBe('brand-new-name');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'slug' => 'brand-new-name',
    ]);
});

test('update product action keeps the slug when the name is unchanged', function () {
    $product = Product::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'custom-stable-slug',
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: 'Stable Name'),
    );

    expect($updated->slug)->toBe('custom-stable-slug');
});

test('update product action generates a unique slug when renamed to a taken name', function () {
    Product::factory()->create([
        'name' => 'Shared Name',
        'slug' => 'shared-name',
    ]);

    $product = Product::factory()->create([
        'name' => 'Other Name',
        'slug' => 'other-name',
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: 'Shared Name'),
    );

    expect($updated->slug)->toBe('shared-name-2');
});

test('update product action does not affect other products', function () {
    $product = Product::factory()->create([
        'name' => 'Target Name',
        'slug' => 'target-name',
    ]);

    $other = Product::factory()->create([
        'name' => 'Other Name',
        'slug' => 'other-name',
        'description' => 'Other description.',
        'is_active' => true,
    ]);

    app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(
            name: 'Renamed Target',
            description: 'Renamed description.',
            isActive: false,
        ),
    );

    $other->refresh();

    expect($other->name)->toBe('Other Name')
        ->and($other->slug)->toBe('other-name')
        ->and($other->description)->toBe('Other description.')
        ->and($other->is_active)->toBeTrue();
});

test('update product action rejects an empty name', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    expect(fn () => app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: ''),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);
});

test('update product action rejects names longer than 255 characters', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    expect(fn () => app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: str_repeat('a', 256)),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);
});
