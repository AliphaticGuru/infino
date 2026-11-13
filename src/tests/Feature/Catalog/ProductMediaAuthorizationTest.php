<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

test('manage media is restricted to admin users', function () {
    $product = Product::factory()->withoutVariant()->create();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('manageMedia', $product))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manageMedia', $product))->toBeFalse();
});

test('product media policy class abilities are restricted to admin users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $abilities = ['viewAny', 'create', 'deleteAny'];

    foreach ($abilities as $ability) {
        expect(Gate::forUser($admin)->allows($ability, ProductMedia::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows($ability, ProductMedia::class))->toBeFalse();
    }
});

test('product media policy model abilities are restricted to admin users', function () {
    $media = ProductMedia::factory()->forProduct(Product::factory()->withoutVariant()->create())->create();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $abilities = ['view', 'update', 'delete', 'setPrimary', 'reorder'];

    foreach ($abilities as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $media))->toBeTrue()
            ->and(Gate::forUser($user)->allows($ability, $media))->toBeFalse();
    }
});

test('guests cannot manage product media', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = ProductMedia::factory()->forProduct($product)->create();

    auth()->logout();

    expect(Gate::allows('manageMedia', $product))->toBeFalse()
        ->and(Gate::allows('viewAny', ProductMedia::class))->toBeFalse()
        ->and(Gate::allows('update', $media))->toBeFalse();
});
