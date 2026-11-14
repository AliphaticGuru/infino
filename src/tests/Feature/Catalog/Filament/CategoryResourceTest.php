<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the category resource lists categories', function () {
    $categories = Category::factory()->count(3)->create();

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords($categories);
});

test('the category resource can search categories by name and slug', function () {
    $matching = Category::factory()->create([
        'name' => 'Studio Audio',
        'slug' => 'studio-audio',
    ]);
    $other = Category::factory()->create([
        'name' => 'Garden Tools',
        'slug' => 'garden-tools',
    ]);

    Livewire::test(ListCategories::class)
        ->searchTable('Studio')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the category resource shows the parent of a child category', function () {
    $parent = Category::factory()->create(['name' => 'Parent Category']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'Child Category']);

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords([$parent, $child])
        ->assertTableColumnStateSet('parent.name', 'Parent Category', $child);
});

test('a category can be created through the existing create category contract', function () {
    $parent = Category::factory()->create();

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Studio Monitors',
            'description' => 'Reference monitors.',
            'parent_id' => $parent->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('categories', [
        'name' => 'Studio Monitors',
        'slug' => 'studio-monitors',
        'description' => 'Reference monitors.',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
});

test('category creation validation is enforced', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    $this->assertDatabaseCount('categories', 0);
});

test('a category can be edited through the existing update category contract', function () {
    $category = Category::factory()->create([
        'name' => 'Old Category',
        'slug' => 'old-category',
        'is_active' => true,
    ]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm([
            'name' => 'Renamed Category',
            'description' => 'Updated description.',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $category->refresh();

    expect($category->name)->toBe('Renamed Category')
        ->and($category->slug)->toBe('renamed-category')
        ->and($category->description)->toBe('Updated description.')
        ->and($category->is_active)->toBeFalse();
});

test('a category can be assigned a parent through the update contract', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => null]);

    Livewire::test(EditCategory::class, ['record' => $child->getRouteKey()])
        ->fillForm([
            'name' => $child->name,
            'parent_id' => $parent->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($child->fresh()->parent_id)->toBe($parent->id);
});

test('a category cannot be made its own parent through the update contract', function () {
    $category = Category::factory()->create();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm([
            'name' => $category->name,
            'parent_id' => $category->id,
        ])
        ->call('save')
        ->assertHasErrors();

    expect($category->fresh()->parent_id)->toBeNull();
});

test('an unassigned category can be deleted through the delete category contract', function () {
    $category = Category::factory()->create();

    Livewire::test(ListCategories::class)
        ->callTableAction('delete', $category);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a category with assigned products cannot be deleted through the table action', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $category->products()->attach($product);

    Livewire::test(ListCategories::class)
        ->callTableAction('delete', $category);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
    $this->assertDatabaseHas('category_product', [
        'category_id' => $category->id,
        'product_id' => $product->id,
    ]);
});

test('admin can load the category resource over http', function () {
    $this->get('/admin/categories')->assertOk();
});
