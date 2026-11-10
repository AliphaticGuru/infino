<?php

use App\Modules\Catalog\Actions\AssignProductCategories;
use App\Modules\Catalog\Actions\RemoveProductCategories;
use App\Modules\Catalog\Actions\SyncProductCategories;
use App\Modules\Catalog\Contracts\AssignsProductCategories;
use App\Modules\Catalog\Contracts\RemovesProductCategories;
use App\Modules\Catalog\Contracts\SyncsProductCategories;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('product category assignment contracts resolve through the catalog module', function () {
    expect(app(AssignsProductCategories::class))->toBeInstanceOf(AssignProductCategories::class)
        ->and(app(RemovesProductCategories::class))->toBeInstanceOf(RemoveProductCategories::class)
        ->and(app(SyncsProductCategories::class))->toBeInstanceOf(SyncProductCategories::class);
});

test('a single category can be assigned to a product', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $result = app(AssignsProductCategories::class)->handle($product, [$category->id]);

    expect($result->is($product))->toBeTrue()
        ->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('category_product', [
        'product_id' => $product->id,
        'category_id' => $category->id,
    ]);
});

test('multiple categories can be assigned to a product', function () {
    $product = Product::factory()->create();
    $categories = Category::factory()->count(3)->create();

    app(AssignsProductCategories::class)->handle($product, $categories->modelKeys());

    expect($product->categories()->count())->toBe(3);

    foreach ($categories as $category) {
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
    }
});

test('assigning categories preserves existing assignments', function () {
    $product = Product::factory()->create();
    $existing = Category::factory()->create();
    $additional = Category::factory()->create();

    $product->categories()->attach($existing);

    app(AssignsProductCategories::class)->handle($product, [$additional->id]);

    expect($product->categories()->count())->toBe(2)
        ->and($product->categories()->whereKey($existing->id)->exists())->toBeTrue()
        ->and($product->categories()->whereKey($additional->id)->exists())->toBeTrue();
});

test('assigning the same category twice does not create duplicate assignments', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $action = app(AssignsProductCategories::class);

    $action->handle($product, [$category->id]);
    $action->handle($product, [$category->id]);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($category->id)->count())->toBe(1);

    $this->assertDatabaseCount('category_product', 1);
});

test('selected categories can be removed from a product', function () {
    $product = Product::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $product->categories()->attach($categories->modelKeys());

    app(RemovesProductCategories::class)->handle($product, [$categories->first()->id]);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($categories->first()->id)->exists())->toBeFalse();

    $this->assertDatabaseMissing('category_product', [
        'product_id' => $product->id,
        'category_id' => $categories->first()->id,
    ]);
});

test('removing categories preserves unrelated assignments', function () {
    $product = Product::factory()->create();
    $removed = Category::factory()->create();
    $kept = Category::factory()->create();

    $product->categories()->attach([$removed->id, $kept->id]);

    app(RemovesProductCategories::class)->handle($product, [$removed->id]);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($kept->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('category_product', [
        'product_id' => $product->id,
        'category_id' => $kept->id,
    ]);
});

test('removing a category that is not assigned is safe', function () {
    $product = Product::factory()->create();
    $assigned = Category::factory()->create();
    $unassigned = Category::factory()->create();

    $product->categories()->attach($assigned);

    app(RemovesProductCategories::class)->handle($product, [$unassigned->id]);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($assigned->id)->exists())->toBeTrue();
});

test('removing a nonexistent category id is safe', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $product->categories()->attach($category);

    app(RemovesProductCategories::class)->handle($product, [999999]);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('synchronising categories replaces the existing assignments', function () {
    $product = Product::factory()->create();
    $kept = Category::factory()->create();
    $removed = Category::factory()->create();
    $added = Category::factory()->create();

    $product->categories()->attach([$kept->id, $removed->id]);

    app(SyncsProductCategories::class)->handle($product, [$kept->id, $added->id]);

    expect($product->categories()->count())->toBe(2)
        ->and($product->categories()->whereKey($kept->id)->exists())->toBeTrue()
        ->and($product->categories()->whereKey($added->id)->exists())->toBeTrue()
        ->and($product->categories()->whereKey($removed->id)->exists())->toBeFalse();

    $this->assertDatabaseMissing('category_product', [
        'product_id' => $product->id,
        'category_id' => $removed->id,
    ]);
});

test('synchronising with an empty array removes all assignments', function () {
    $product = Product::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $product->categories()->attach($categories->modelKeys());

    app(SyncsProductCategories::class)->handle($product, []);

    expect($product->categories()->count())->toBe(0);

    $this->assertDatabaseCount('category_product', 0);
});

test('assigning a nonexistent category id is rejected without changing assignments', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $product->categories()->attach($category);

    expect(fn () => app(AssignsProductCategories::class)->handle($product, [$category->id, 999999]))
        ->toThrow(ValidationException::class);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();

    $this->assertDatabaseCount('category_product', 1);
});

test('synchronising with a nonexistent category id does not partially modify assignments', function () {
    $product = Product::factory()->create();
    $existing = Category::factory()->create();
    $added = Category::factory()->create();

    $product->categories()->attach($existing);

    expect(fn () => app(SyncsProductCategories::class)->handle($product, [$added->id, 999999]))
        ->toThrow(ValidationException::class);

    expect($product->categories()->count())->toBe(1)
        ->and($product->categories()->whereKey($existing->id)->exists())->toBeTrue()
        ->and($product->categories()->whereKey($added->id)->exists())->toBeFalse();

    $this->assertDatabaseMissing('category_product', [
        'product_id' => $product->id,
        'category_id' => $added->id,
    ]);
});

test('duplicate category ids are rejected', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    expect(fn () => app(AssignsProductCategories::class)->handle($product, [$category->id, $category->id]))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('category_product', 0);
});

test('category assignment fails for a deleted product', function (string $contract) {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $product->delete();

    expect(fn () => app($contract)->handle($product, [$category->id]))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('category_product', 0);
})->with([
    'assign' => [AssignsProductCategories::class],
    'remove' => [RemovesProductCategories::class],
    'sync' => [SyncsProductCategories::class],
]);

test('category assignment fails for a product that was never persisted', function () {
    $product = new Product(['name' => 'Unsaved', 'slug' => 'unsaved']);
    $category = Category::factory()->create();

    expect(fn () => app(AssignsProductCategories::class)->handle($product, [$category->id]))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('category_product', 0);
});
