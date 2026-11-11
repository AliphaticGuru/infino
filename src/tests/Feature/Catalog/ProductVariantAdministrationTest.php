<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\FindProductVariant;
use App\Modules\Catalog\Actions\RepositionProductVariants;
use App\Modules\Catalog\Actions\TransitionProductVariantStatus;
use App\Modules\Catalog\Actions\UpdateProductVariant;
use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Contracts\FindsProductVariants;
use App\Modules\Catalog\Contracts\RepositionsProductVariants;
use App\Modules\Catalog\Contracts\TransitionsProductVariantStatus;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Data\UpdateProductVariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function createProductVariantFor(Product $product, array $attributes = []): ProductVariant
{
    return app(CreatesProductVariants::class)->handle(
        $product,
        new CreateProductVariantData(
            sku: $attributes['sku'] ?? null,
            label: $attributes['label'] ?? null,
            variantKey: $attributes['variant_key'] ?? null,
            isActive: $attributes['is_active'] ?? true,
        ),
    );
}

test('variant contracts resolve to their actions', function () {
    expect(app(FindsProductVariants::class))->toBeInstanceOf(FindProductVariant::class)
        ->and(app(CreatesProductVariants::class))->toBeInstanceOf(CreateProductVariant::class)
        ->and(app(UpdatesProductVariants::class))->toBeInstanceOf(UpdateProductVariant::class)
        ->and(app(TransitionsProductVariantStatus::class))->toBeInstanceOf(TransitionProductVariantStatus::class)
        ->and(app(RepositionsProductVariants::class))->toBeInstanceOf(RepositionProductVariants::class);
});

test('variants can be retrieved for a product in position order', function () {
    $product = Product::factory()->create();
    $second = createProductVariantFor($product, ['sku' => 'ORDER-2', 'label' => 'Second']);
    $third = createProductVariantFor($product, ['sku' => 'ORDER-3', 'label' => 'Third']);

    $variants = app(FindsProductVariants::class)->forProduct($product);

    expect($variants->pluck('id')->all())->toBe([
        $product->defaultVariant->id,
        $second->id,
        $third->id,
    ])->and($variants->pluck('position')->all())->toBe([0, 1, 2]);
});

test('an individual variant can be retrieved by id', function () {
    $variant = ProductVariant::factory()->create();

    expect(app(FindsProductVariants::class)->find($variant->id)->is($variant))->toBeTrue();
});

test('variant retrieval can be scoped to a product', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    expect(app(FindsProductVariants::class)->findForProduct($product, $product->defaultVariant->id)->is($product->defaultVariant))->toBeTrue();

    expect(fn () => app(FindsProductVariants::class)->findForProduct($product, $other->defaultVariant->id))
        ->toThrow(ModelNotFoundException::class);
});

test('retrieving variants for a missing product fails', function () {
    $product = Product::factory()->create();
    $product->delete();

    expect(fn () => app(FindsProductVariants::class)->forProduct($product))
        ->toThrow(ModelNotFoundException::class);
});

test('additional variants can be created for a product', function () {
    $product = Product::factory()->create();
    $default = $product->defaultVariant;

    $variant = createProductVariantFor($product, [
        'sku' => 'VARIANT-001',
        'label' => 'Large',
        'variant_key' => 'size:large',
    ]);

    expect($variant->product_id)->toBe($product->id)
        ->and($variant->sku)->toBe('VARIANT-001')
        ->and($variant->label)->toBe('Large')
        ->and($variant->variant_key)->toBe('size:large')
        ->and($variant->is_active)->toBeTrue()
        ->and($variant->position)->toBe(1)
        ->and($variant->isDefault())->toBeFalse()
        ->and($product->fresh()->defaultVariant->is($default))->toBeTrue();
});

test('multiple variants per product receive consistent positions', function () {
    $product = Product::factory()->create();

    $second = createProductVariantFor($product, ['sku' => 'POS-2']);
    $third = createProductVariantFor($product, ['sku' => 'POS-3']);
    $fourth = createProductVariantFor($product, ['sku' => 'POS-4']);

    expect([$second->position, $third->position, $fourth->position])->toBe([1, 2, 3])
        ->and($product->variants()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2, 3]);
});

test('variant creation generates a collision-safe sku when none is supplied', function () {
    $product = Product::factory()->create();

    $variant = createProductVariantFor($product);

    expect($variant->sku)->toBe('SKU-'.$product->id.'-2')
        ->and($variant->label)->toBeNull();
});

test('creating the first variant of a product preserves the default invariant', function () {
    $product = Product::factory()->withoutVariant()->create();

    $variant = createProductVariantFor($product);

    expect($variant->position)->toBe(0)
        ->and($variant->label)->toBe('Default')
        ->and($variant->is_active)->toBeTrue()
        ->and($variant->isDefault())->toBeTrue();
});

test('variant creation rejects duplicate skus', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    createProductVariantFor($product, ['sku' => 'DUP-VARIANT']);

    expect(fn () => createProductVariantFor($other, ['sku' => 'DUP-VARIANT']))
        ->toThrow(ValidationException::class);

    expect($other->variants()->count())->toBe(1);
});

test('variant keys must be unique per product', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    createProductVariantFor($product, ['sku' => 'KEY-1', 'variant_key' => 'color:red']);

    expect(fn () => createProductVariantFor($product, ['sku' => 'KEY-2', 'variant_key' => 'color:red']))
        ->toThrow(ValidationException::class);

    $otherVariant = createProductVariantFor($other, ['sku' => 'KEY-3', 'variant_key' => 'color:red']);

    expect($otherVariant->variant_key)->toBe('color:red');
});

test('variant creation rolls back when persistence fails', function () {
    $product = Product::factory()->create();

    ProductVariant::creating(function (): void {
        throw new RuntimeException('Forced variant creation failure.');
    });

    try {
        expect(fn () => createProductVariantFor($product, ['sku' => 'ROLLBACK-1']))
            ->toThrow(RuntimeException::class);
    } finally {
        ProductVariant::flushEventListeners();
    }

    expect($product->variants()->count())->toBe(1);
});

test('a variant can be updated without changing its product', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $variant = createProductVariantFor($product, ['sku' => 'UPDATE-1', 'label' => 'Old']);

    $updated = app(UpdatesProductVariants::class)->handle(
        $variant,
        new UpdateProductVariantData(
            sku: 'UPDATE-2',
            label: 'New label',
            variantKey: 'size:medium',
        ),
    );

    expect($updated->sku)->toBe('UPDATE-2')
        ->and($updated->label)->toBe('New label')
        ->and($updated->variant_key)->toBe('size:medium')
        ->and($updated->product_id)->toBe($product->id)
        ->and($updated->product_id)->not->toBe($other->id);
});

test('variant updates reject duplicate skus and variant keys', function () {
    $product = Product::factory()->create();
    $first = createProductVariantFor($product, ['sku' => 'UPD-DUP-1', 'variant_key' => 'size:1']);
    $second = createProductVariantFor($product, ['sku' => 'UPD-DUP-2', 'variant_key' => 'size:2']);

    expect(fn () => app(UpdatesProductVariants::class)->handle(
        $second,
        new UpdateProductVariantData(sku: 'UPD-DUP-1'),
    ))->toThrow(ValidationException::class);

    expect(fn () => app(UpdatesProductVariants::class)->handle(
        $second,
        new UpdateProductVariantData(variantKey: 'size:1'),
    ))->toThrow(ValidationException::class);

    expect($second->fresh()->sku)->toBe('UPD-DUP-2')
        ->and($second->fresh()->variant_key)->toBe('size:2')
        ->and($first->fresh()->sku)->toBe('UPD-DUP-1');
});

test('a variant update ignores its own sku and variant key uniqueness', function () {
    $product = Product::factory()->create();
    $variant = createProductVariantFor($product, ['sku' => 'SELF-1', 'variant_key' => 'size:self']);

    $updated = app(UpdatesProductVariants::class)->handle(
        $variant,
        new UpdateProductVariantData(sku: 'SELF-1', variantKey: 'size:self'),
    );

    expect($updated->sku)->toBe('SELF-1')
        ->and($updated->variant_key)->toBe('size:self');
});

test('variant updates preserve inventory and stock movement relationships', function () {
    $variant = ProductVariant::factory()->create();
    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 12]);
    $movement = StockMovement::factory()->forVariant($variant)->create([
        'quantity_delta' => 5,
        'resulting_quantity' => 12,
    ]);

    app(UpdatesProductVariants::class)->handle(
        $variant,
        new UpdateProductVariantData(label: 'Renamed variant'),
    );

    expect($inventory->fresh()->variant_id)->toBe($variant->id)
        ->and($inventory->fresh()->quantity)->toBe(12)
        ->and($movement->fresh()->variant_id)->toBe($variant->id)
        ->and($movement->fresh()->quantity_delta)->toBe(5)
        ->and($movement->fresh()->resulting_quantity)->toBe(12);
});

test('a non-default variant can be deactivated and reactivated', function () {
    $product = Product::factory()->create();
    $variant = createProductVariantFor($product, ['sku' => 'LIFE-1']);

    $deactivated = app(TransitionsProductVariantStatus::class)->deactivate($variant);

    expect($deactivated->is_active)->toBeFalse();

    $activated = app(TransitionsProductVariantStatus::class)->activate($variant);

    expect($activated->is_active)->toBeTrue();
});

test('already active and already inactive lifecycle operations are rejected', function () {
    $product = Product::factory()->create();
    $variant = createProductVariantFor($product, ['sku' => 'LIFE-2']);

    expect(fn () => app(TransitionsProductVariantStatus::class)->activate($variant))
        ->toThrow(ValidationException::class);

    app(TransitionsProductVariantStatus::class)->deactivate($variant);

    expect(fn () => app(TransitionsProductVariantStatus::class)->deactivate($variant))
        ->toThrow(ValidationException::class);
});

test('the default variant cannot be deactivated', function () {
    $product = Product::factory()->create();
    $default = $product->defaultVariant;

    expect(fn () => app(TransitionsProductVariantStatus::class)->deactivate($default))
        ->toThrow(ValidationException::class);

    expect($default->fresh()->is_active)->toBeTrue();
});

test('the last active variant cannot be deactivated', function () {
    $product = Product::factory()->create();
    $default = $product->defaultVariant;
    $variant = createProductVariantFor($product, ['sku' => 'LIFE-3']);

    $default->forceFill(['is_active' => false])->save();

    expect(fn () => app(TransitionsProductVariantStatus::class)->deactivate($variant))
        ->toThrow(ValidationException::class);

    expect($variant->fresh()->is_active)->toBeTrue();
});

test('variant lifecycle changes preserve inventory and movement history', function () {
    $product = Product::factory()->create();
    $variant = createProductVariantFor($product, ['sku' => 'LIFE-4']);
    $inventory = Inventory::factory()->forVariant($variant)->create(['quantity' => 8]);
    $movement = StockMovement::factory()->forVariant($variant)->create([
        'quantity_delta' => -2,
        'resulting_quantity' => 8,
    ]);

    app(TransitionsProductVariantStatus::class)->deactivate($variant);

    expect($inventory->fresh()->variant_id)->toBe($variant->id)
        ->and($inventory->fresh()->quantity)->toBe(8)
        ->and($movement->fresh()->variant_id)->toBe($variant->id)
        ->and($movement->fresh()->quantity_delta)->toBe(-2);
});

test('variants can be repositioned within a product', function () {
    $product = Product::factory()->create();
    $first = $product->defaultVariant;
    $second = createProductVariantFor($product, ['sku' => 'REORDER-2']);
    $third = createProductVariantFor($product, ['sku' => 'REORDER-3']);

    $ordered = app(RepositionsProductVariants::class)->handle(
        $product,
        [$third->id, $first->id, $second->id],
    );

    expect($ordered->pluck('id')->all())->toBe([$third->id, $first->id, $second->id])
        ->and($ordered->pluck('position')->all())->toBe([0, 1, 2])
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2)
        ->and($third->fresh()->position)->toBe(0);
});

test('repositioning rejects duplicate variants', function () {
    $product = Product::factory()->create();
    $second = createProductVariantFor($product, ['sku' => 'REORDER-4']);

    expect(fn () => app(RepositionsProductVariants::class)->handle(
        $product,
        [$product->defaultVariant->id, $product->defaultVariant->id, $second->id],
    ))->toThrow(ValidationException::class);
});

test('repositioning rejects missing and foreign variants', function () {
    $product = Product::factory()->create();
    $second = createProductVariantFor($product, ['sku' => 'REORDER-5']);
    $other = Product::factory()->create();

    expect(fn () => app(RepositionsProductVariants::class)->handle(
        $product,
        [$product->defaultVariant->id],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(RepositionsProductVariants::class)->handle(
        $product,
        [$product->defaultVariant->id, $other->defaultVariant->id],
    ))->toThrow(ValidationException::class);

    expect($product->defaultVariant->fresh()->position)->toBe(0)
        ->and($second->fresh()->position)->toBe(1)
        ->and($other->defaultVariant->fresh()->position)->toBe(0);
});

test('repositioning one product does not affect another product', function () {
    $product = Product::factory()->create();
    $second = createProductVariantFor($product, ['sku' => 'REORDER-6']);
    $other = Product::factory()->create();
    $otherSecond = createProductVariantFor($other, ['sku' => 'REORDER-7']);

    app(RepositionsProductVariants::class)->handle($product, [$second->id, $product->defaultVariant->id]);

    expect($product->defaultVariant->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(0)
        ->and($other->defaultVariant->fresh()->position)->toBe(0)
        ->and($otherSecond->fresh()->position)->toBe(1);
});

test('variant policies deny non-admin users', function () {
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny', 'reorder'];
    $modelAbilities = ['view', 'update', 'delete', 'activate', 'deactivate'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, ProductVariant::class))->toBeFalse();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $variant))->toBeFalse();
    }
});

test('variant policies allow administration but not deletion for admin users', function () {
    $user = User::factory()->admin()->create();
    $variant = ProductVariant::factory()->create();

    $classAbilities = ['viewAny', 'create', 'reorder'];
    $modelAbilities = ['view', 'update', 'activate', 'deactivate'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, ProductVariant::class))->toBeTrue();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($user)->allows($ability, $variant))->toBeTrue();
    }

    expect(Gate::forUser($user)->allows('delete', $variant))->toBeFalse()
        ->and(Gate::forUser($user)->allows('deleteAny', ProductVariant::class))->toBeFalse();
});
