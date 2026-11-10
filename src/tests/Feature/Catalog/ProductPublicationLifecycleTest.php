<?php

use App\Modules\Catalog\Actions\TransitionProductStatus;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Data\UpdateProductData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('products table has a status column', function () {
    expect(Schema::hasColumn('products', 'status'))->toBeTrue();
});

test('existing product rows receive the draft status by default', function () {
    DB::table('products')->insert([
        'name' => 'Legacy Product',
        'slug' => 'legacy-product',
        'description' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->where('slug', 'legacy-product')->sole();

    expect($product->status)->toBe(ProductStatus::Draft);
});

test('product status transition contract resolves through the catalog module', function () {
    expect(app(TransitionsProductStatus::class))
        ->toBeInstanceOf(TransitionProductStatus::class);
});

test('newly created products default to draft status', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: 'Draft Product'),
    );

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'status' => 'draft',
    ]);
});

test('product factory defaults to draft and supports published and archived states', function () {
    expect(Product::factory()->create()->status)->toBe(ProductStatus::Draft)
        ->and(Product::factory()->published()->create()->status)->toBe(ProductStatus::Published)
        ->and(Product::factory()->archived()->create()->status)->toBe(ProductStatus::Archived);
});

test('draft products can be published', function () {
    $product = Product::factory()->create([
        'name' => 'Draft Product',
        'slug' => 'draft-product',
        'status' => ProductStatus::Draft,
    ]);

    $published = app(TransitionsProductStatus::class)->publish($product);

    expect($published->status)->toBe(ProductStatus::Published);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'status' => 'published',
    ]);
});

test('published products can be returned to draft', function () {
    $product = Product::factory()->published()->create();

    $unpublished = app(TransitionsProductStatus::class)->unpublish($product);

    expect($unpublished->status)->toBe(ProductStatus::Draft);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'status' => 'draft',
    ]);
});

test('draft products can be archived', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Draft,
    ]);

    $archived = app(TransitionsProductStatus::class)->archive($product);

    expect($archived->status)->toBe(ProductStatus::Archived);
});

test('published products can be archived', function () {
    $product = Product::factory()->published()->create();

    $archived = app(TransitionsProductStatus::class)->archive($product);

    expect($archived->status)->toBe(ProductStatus::Archived);
});

test('archived products can be restored to draft and then published', function () {
    $product = Product::factory()->archived()->create();
    $action = app(TransitionsProductStatus::class);

    $restored = $action->restore($product);

    expect($restored->status)->toBe(ProductStatus::Draft);

    $published = $action->publish($restored);

    expect($published->status)->toBe(ProductStatus::Published);
});

test('invalid lifecycle transitions are rejected', function (ProductStatus $from, string $operation) {
    $product = Product::factory()->create(['status' => $from]);
    $action = app(TransitionsProductStatus::class);

    expect(fn () => $action->{$operation}($product))
        ->toThrow(ValidationException::class);

    expect($product->fresh()->status)->toBe($from);
})->with([
    'publish a published product' => [ProductStatus::Published, 'publish'],
    'publish an archived product' => [ProductStatus::Archived, 'publish'],
    'unpublish a draft product' => [ProductStatus::Draft, 'unpublish'],
    'unpublish an archived product' => [ProductStatus::Archived, 'unpublish'],
    'archive an archived product' => [ProductStatus::Archived, 'archive'],
    'restore a draft product' => [ProductStatus::Draft, 'restore'],
    'restore a published product' => [ProductStatus::Published, 'restore'],
]);

test('lifecycle transitions fail for a deleted product', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Draft,
    ]);

    $product->delete();

    expect(fn () => app(TransitionsProductStatus::class)->publish($product))
        ->toThrow(ModelNotFoundException::class);
});

test('lifecycle transitions preserve unrelated product attributes', function () {
    $product = Product::factory()->create([
        'name' => 'Original Name',
        'slug' => 'original-name',
        'description' => 'Original description.',
        'is_active' => false,
        'status' => ProductStatus::Draft,
    ]);

    $action = app(TransitionsProductStatus::class);

    $action->publish($product);
    $action->unpublish($product);
    $action->archive($product);
    $restored = $action->restore($product);

    expect($restored->name)->toBe('Original Name')
        ->and($restored->slug)->toBe('original-name')
        ->and($restored->description)->toBe('Original description.')
        ->and($restored->is_active)->toBeFalse()
        ->and($restored->status)->toBe(ProductStatus::Draft);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Original Name',
        'slug' => 'original-name',
        'description' => 'Original description.',
        'is_active' => false,
        'status' => 'draft',
    ]);
});

test('operational availability is independent of publication status', function () {
    $action = app(TransitionsProductStatus::class);

    $activeProduct = Product::factory()->create([
        'is_active' => true,
        'status' => ProductStatus::Draft,
    ]);

    $action->publish($activeProduct);
    $action->archive($activeProduct);

    expect($activeProduct->fresh()->is_active)->toBeTrue()
        ->and($activeProduct->fresh()->status)->toBe(ProductStatus::Archived);

    $inactiveProduct = Product::factory()->create([
        'is_active' => false,
        'status' => ProductStatus::Draft,
    ]);

    $publishedInactive = $action->publish($inactiveProduct);

    expect($publishedInactive->is_active)->toBeFalse()
        ->and($publishedInactive->status)->toBe(ProductStatus::Published);
});

test('product updates do not change the publication status', function () {
    $product = Product::factory()->published()->create([
        'is_active' => false,
    ]);

    $updated = app(UpdatesProducts::class)->handle(
        $product,
        new UpdateProductData(name: 'Renamed Product'),
    );

    expect($updated->status)->toBe(ProductStatus::Published)
        ->and($updated->is_active)->toBeFalse()
        ->and($updated->name)->toBe('Renamed Product')
        ->and($updated->slug)->toBe('renamed-product');
});
