<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('guests are redirected away from the catalog admin resources', function () {
    $this->get('/admin/products')->assertRedirect('/login');
    $this->get('/admin/categories')->assertRedirect('/login');
});

test('non-admin users cannot access the catalog admin resources', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/products')->assertForbidden();
    $this->get('/admin/categories')->assertForbidden();
});

test('admin users can access the catalog admin resources', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/categories')->assertOk();
});

test('panel access is decided by the user admin flag', function () {
    $panel = Filament::getPanel('admin');

    expect(User::factory()->create()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->admin()->create()->canAccessPanel($panel))->toBeTrue();
});

test('product policies deny every ability to non-admin users', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny'];
    $modelAbilities = ['view', 'update', 'delete', 'publish', 'manageCategories'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, Product::class))->toBeFalse();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $product))->toBeFalse();
    }
});

test('product policies allow every ability to admin users', function () {
    $user = User::factory()->admin()->create();
    $product = Product::factory()->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny'];
    $modelAbilities = ['view', 'update', 'delete', 'publish', 'manageCategories'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, Product::class))->toBeTrue();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $product))->toBeTrue();
    }
});

test('category policies deny every ability to non-admin users', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny'];
    $modelAbilities = ['view', 'update', 'delete'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, Category::class))->toBeFalse();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $category))->toBeFalse();
    }
});

test('category policies allow every ability to admin users', function () {
    $user = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny'];
    $modelAbilities = ['view', 'update', 'delete'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, Category::class))->toBeTrue();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $category))->toBeTrue();
    }
});

test('non-admin users cannot mount the product resource page directly', function () {
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ListProducts::class)->assertForbidden();
});

test('non-admin users cannot mount the category resource page directly', function () {
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ListCategories::class)->assertForbidden();
});

test('the product resource exposes no bulk actions that could bypass authorization', function () {
    $this->actingAs(User::factory()->admin()->create());

    $table = Livewire::test(ListProducts::class)->instance()->getTable();

    expect($table->getBulkActions())->toBe([]);
});

test('the category resource exposes no bulk actions that could bypass authorization', function () {
    $this->actingAs(User::factory()->admin()->create());

    $table = Livewire::test(ListCategories::class)->instance()->getTable();

    expect($table->getBulkActions())->toBe([]);
});
