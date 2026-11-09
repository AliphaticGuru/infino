<?php

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Data\CreateCategoryData;
use App\Modules\Catalog\Data\UpdateCategoryData;
use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('category tables have the expected schema', function () {
    expect(Schema::hasTable('categories'))->toBeTrue()
        ->and(Schema::hasColumns('categories', [
            'id',
            'parent_id',
            'name',
            'slug',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('category_product'))->toBeTrue()
        ->and(Schema::hasColumns('category_product', [
            'category_id',
            'product_id',
        ]))->toBeTrue();
});

test('category contracts resolve through the catalog module', function () {
    expect(app(CreatesCategories::class))->toBeInstanceOf(CreateCategory::class)
        ->and(app(UpdatesCategories::class))->toBeInstanceOf(UpdateCategory::class);
});

test('category factory creates a persisted category', function () {
    $factory = Category::factory();

    expect($factory)->toBeInstanceOf(CategoryFactory::class);

    $category = $factory->create();

    expect($category->exists)->toBeTrue()
        ->and($category->name)->not->toBeEmpty()
        ->and($category->slug)->not->toBeEmpty()
        ->and($category->parent_id)->toBeNull()
        ->and($category->is_active)->toBeTrue();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'slug' => $category->slug,
    ]);
});

test('create category action persists a category', function () {
    $parent = Category::factory()->create();

    $category = app(CreatesCategories::class)->handle(
        new CreateCategoryData(
            name: 'Wireless Headphones',
            description: 'Portable audio equipment.',
            parentId: $parent->id,
            isActive: false,
        ),
    );

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->name)->toBe('Wireless Headphones')
        ->and($category->slug)->toBe('wireless-headphones')
        ->and($category->description)->toBe('Portable audio equipment.')
        ->and($category->parent_id)->toBe($parent->id)
        ->and($category->is_active)->toBeFalse();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'parent_id' => $parent->id,
        'slug' => 'wireless-headphones',
        'is_active' => false,
    ]);
});

test('create category action applies optional defaults', function () {
    $category = app(CreatesCategories::class)->handle(
        new CreateCategoryData(name: 'Audio'),
    );

    expect($category->description)->toBeNull()
        ->and($category->parent_id)->toBeNull()
        ->and($category->is_active)->toBeTrue();
});

test('create category action generates unique slugs for duplicate names', function () {
    $action = app(CreatesCategories::class);
    $data = new CreateCategoryData(name: 'Wireless Headphones');

    $firstCategory = $action->handle($data);
    $secondCategory = $action->handle($data);
    $thirdCategory = $action->handle($data);

    expect($firstCategory->slug)->toBe('wireless-headphones')
        ->and($secondCategory->slug)->toBe('wireless-headphones-2')
        ->and($thirdCategory->slug)->toBe('wireless-headphones-3');

    $this->assertDatabaseCount('categories', 3);
});

test('create category action falls back to a default slug when the name has no slug characters', function () {
    $category = app(CreatesCategories::class)->handle(
        new CreateCategoryData(name: '!!!'),
    );

    expect($category->slug)->toBe('category');
});

test('categories expose parent and child relationships', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->contains($child))->toBeTrue()
        ->and($parent->isDescendantOf($child))->toBeFalse()
        ->and($child->isDescendantOf($parent))->toBeTrue();
});

test('categories and products expose a many-to-many relationship', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();

    $category->products()->attach($product);

    expect($category->products()->whereKey($product->id)->exists())->toBeTrue()
        ->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('category_product', [
        'category_id' => $category->id,
        'product_id' => $product->id,
    ]);

    $category->products()->detach($product);

    $this->assertDatabaseMissing('category_product', [
        'category_id' => $category->id,
        'product_id' => $product->id,
    ]);
});

test('update category action persists changes', function () {
    $category = Category::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    $updated = app(UpdatesCategories::class)->handle(
        $category,
        new UpdateCategoryData(
            name: 'New Name',
            description: 'Updated description.',
            isActive: false,
        ),
    );

    expect($updated->name)->toBe('New Name')
        ->and($updated->slug)->toBe('new-name')
        ->and($updated->description)->toBe('Updated description.')
        ->and($updated->parent_id)->toBeNull()
        ->and($updated->is_active)->toBeFalse();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'New Name',
        'slug' => 'new-name',
        'is_active' => false,
    ]);
});

test('update category action keeps the slug when the name is unchanged', function () {
    $category = Category::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'custom-stable-slug',
    ]);

    $updated = app(UpdatesCategories::class)->handle(
        $category,
        new UpdateCategoryData(name: 'Stable Name'),
    );

    expect($updated->slug)->toBe('custom-stable-slug');
});

test('update category action preserves omitted optional fields', function () {
    $parent = Category::factory()->create();

    $category = Category::factory()->childOf($parent)->create([
        'description' => 'Existing description.',
        'is_active' => false,
    ]);

    $updated = app(UpdatesCategories::class)->handle(
        $category,
        new UpdateCategoryData(name: 'Renamed Category'),
    );

    expect($updated->parent_id)->toBe($parent->id)
        ->and($updated->description)->toBe('Existing description.')
        ->and($updated->is_active)->toBeFalse();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'parent_id' => $parent->id,
        'description' => 'Existing description.',
        'is_active' => false,
    ]);
});

test('update category action generates a unique slug when renamed', function () {
    Category::factory()->create([
        'name' => 'Shared Name',
        'slug' => 'shared-name',
    ]);

    $category = Category::factory()->create([
        'name' => 'Other Name',
        'slug' => 'other-name',
    ]);

    $updated = app(UpdatesCategories::class)->handle(
        $category,
        new UpdateCategoryData(name: 'Shared Name'),
    );

    expect($updated->slug)->toBe('shared-name-2');
});

test('update category action reassigns the parent', function () {
    $oldParent = Category::factory()->create();
    $newParent = Category::factory()->create();
    $category = Category::factory()->childOf($oldParent)->create();

    $updated = app(UpdatesCategories::class)->handle(
        $category,
        new UpdateCategoryData(
            name: $category->name,
            parentId: $newParent->id,
        ),
    );

    expect($updated->parent_id)->toBe($newParent->id)
        ->and($updated->parent->is($newParent))->toBeTrue();
});

test('update category action rejects invalid, self and descendant parents', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();
    $action = app(UpdatesCategories::class);

    expect(fn () => $action->handle(
        $parent,
        new UpdateCategoryData(name: $parent->name, parentId: 999999),
    ))->toThrow(ValidationException::class);

    expect(fn () => $action->handle(
        $parent,
        new UpdateCategoryData(name: $parent->name, parentId: $parent->id),
    ))->toThrow(ValidationException::class);

    expect(fn () => $action->handle(
        $parent,
        new UpdateCategoryData(name: $parent->name, parentId: $child->id),
    ))->toThrow(ValidationException::class);
});

test('create category action validates the name and parent', function () {
    $action = app(CreatesCategories::class);

    expect(fn () => $action->handle(new CreateCategoryData(name: '')))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->handle(new CreateCategoryData(name: str_repeat('a', 256))))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->handle(
        new CreateCategoryData(name: 'Orphan', parentId: 999999),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('categories', 0);
});

test('deleting a parent category promotes its children to root categories', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    $parent->delete();

    expect($child->fresh()->parent_id)->toBeNull();
});
