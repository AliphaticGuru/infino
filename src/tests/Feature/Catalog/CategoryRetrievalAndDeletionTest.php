<?php

use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\FindCategory;
use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Contracts\FindsCategories;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

test('category retrieval and deletion contracts resolve through the catalog module', function () {
    expect(app(FindsCategories::class))->toBeInstanceOf(FindCategory::class)
        ->and(app(DeletesCategories::class))->toBeInstanceOf(DeleteCategory::class);
});

test('a category can be retrieved by id', function () {
    $category = Category::factory()->create([
        'name' => 'Audio',
        'slug' => 'audio',
    ]);

    $found = app(FindsCategories::class)->find($category->id);

    expect($found->is($category))->toBeTrue()
        ->and($found->name)->toBe('Audio')
        ->and($found->slug)->toBe('audio');
});

test('retrieving a nonexistent category id fails', function () {
    expect(fn () => app(FindsCategories::class)->find(999999))
        ->toThrow(ModelNotFoundException::class);
});

test('categories can be retrieved with pagination', function () {
    Category::factory()->count(25)->create();

    $paginator = app(FindsCategories::class)->paginate();

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(25)
        ->and($paginator->perPage())->toBe(20)
        ->and(count($paginator->items()))->toBe(20);

    $secondPage = app(FindsCategories::class)->paginate(20, 2);

    expect(count($secondPage->items()))->toBe(5);
});

test('category pagination honours a custom page size and orders by id', function () {
    $categories = Category::factory()->count(5)->create();

    $paginator = app(FindsCategories::class)->paginate(2);

    expect($paginator->perPage())->toBe(2)
        ->and(count($paginator->items()))->toBe(2)
        ->and($paginator->items()[0]->id)->toBe($categories->first()->id)
        ->and($paginator->items()[1]->id)->toBe($categories->get(1)->id);
});

test('products belonging to a category can be retrieved with pagination', function () {
    $category = Category::factory()->create();
    $assigned = Product::factory()->count(3)->create();
    $unassigned = Product::factory()->create();

    $category->products()->attach($assigned->modelKeys());

    $paginator = app(FindsCategories::class)->products($category, 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(3)
        ->and($paginator->perPage())->toBe(2)
        ->and(count($paginator->items()))->toBe(2)
        ->and(collect($paginator->items())->pluck('id')->all())
        ->toBe($assigned->take(2)->pluck('id')->all())
        ->and(collect($paginator->items())->pluck('id'))->not->toContain($unassigned->id);
});

test('a category without products returns an empty product paginator', function () {
    $category = Category::factory()->create();

    $paginator = app(FindsCategories::class)->products($category);

    expect($paginator->total())->toBe(0)
        ->and($paginator->items())->toBe([]);
});

test('retrieving products for a deleted category fails', function () {
    $category = Category::factory()->create();
    $category->delete();

    expect(fn () => app(FindsCategories::class)->products($category))
        ->toThrow(ModelNotFoundException::class);
});

test('an unassigned category can be deleted', function () {
    $category = Category::factory()->create();

    app(DeletesCategories::class)->handle($category);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a category with assigned products cannot be deleted', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $category->products()->attach($product);

    expect(fn () => app(DeletesCategories::class)->handle($category))
        ->toThrow(
            ValidationException::class,
            'A category with assigned products cannot be deleted.',
        );

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('a rejected category deletion preserves product-category relationships', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $category->products()->attach($product);

    expect(fn () => app(DeletesCategories::class)->handle($category))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('category_product', [
        'category_id' => $category->id,
        'product_id' => $product->id,
    ]);

    expect($product->fresh())->not->toBeNull()
        ->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('deleting a nonexistent category fails', function () {
    $category = Category::factory()->create();
    $category->delete();

    expect(fn () => app(DeletesCategories::class)->handle($category))
        ->toThrow(ModelNotFoundException::class);
});

test('deleting a parent category promotes its children to root categories', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    app(DeletesCategories::class)->handle($parent);

    expect($child->fresh()->parent_id)->toBeNull()
        ->and($child->fresh())->not->toBeNull();
});
