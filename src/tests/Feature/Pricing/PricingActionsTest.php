<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\FindVariantPrices;
use App\Modules\Pricing\Actions\RemoveVariantPrice;
use App\Modules\Pricing\Actions\SetVariantPrice;
use App\Modules\Pricing\Contracts\FindsVariantPrices;
use App\Modules\Pricing\Contracts\RemovesVariantPrice;
use App\Modules\Pricing\Contracts\SetsVariantPrice;
use App\Modules\Pricing\Data\SetVariantPriceData;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Models\VariantPriceChange;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('contracts resolve through the container', function () {
    $finds = app(FindsVariantPrices::class);
    $sets = app(SetsVariantPrice::class);
    $removes = app(RemovesVariantPrice::class);

    expect($finds)->toBeInstanceOf(FindVariantPrices::class);
    expect($sets)->toBeInstanceOf(SetVariantPrice::class);
    expect($removes)->toBeInstanceOf(RemoveVariantPrice::class);
});

test('creating a price for a variant', function () {
    $variant = ProductVariant::factory()->create();

    $data = new SetVariantPriceData(
        variantId: $variant->id,
        currency: 'NGN',
        amountMinor: 50000,  // 500.00 NGN
        compareAtAmountMinor: 60000,  // 600.00 NGN
    );

    $action = app(SetVariantPrice::class);
    $price = $action->handle(
        $data->variantId,
        $data->currency,
        $data->amountMinor,
        $data->compareAtAmountMinor,
    );

    expect($price)->toBeInstanceOf(VariantPrice::class)
        ->and($price->variant_id)->toBe($variant->id)
        ->and($price->currency)->toBe('NGN')
        ->and($price->amount_minor)->toBe(50000)
        ->and($price->compare_at_amount_minor)->toBe(60000);
});

test('creating a price with zero amount is allowed', function () {
    $variant = ProductVariant::factory()->create();

    $action = app(SetVariantPrice::class);
    $price = $action->handle(
        $variant->id,
        'NGN',
        0,
    );

    expect($price->amount_minor)->toBe(0);
});

test('different currencies are stored separately', function () {
    $variant = ProductVariant::factory()->create();

    $action = app(SetVariantPrice::class);

    // Create USD price
    $usdPrice = $action->handle(
        $variant->id,
        'USD',
        1234,  // 12.34 USD
        1500,  // 15.00 USD compare-at
    );

    // Create NGN price
    $ngnPrice = $action->handle(
        $variant->id,
        'NGN',
        50000,  // 500.00 NGN
        60000,  // 600.00 NGN compare-at
    );

    expect($usdPrice->currency)->toBe('USD');
    expect($usdPrice->amount_minor)->toBe(1234);
    expect($ngnPrice->currency)->toBe('NGN');
    expect($ngnPrice->amount_minor)->toBe(50000);
});

test('retrieving a price by variant and currency', function () {
    $variant = ProductVariant::factory()->create();

    // Create a price first
    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1234,
        1500,
    );

    $finds = app(FindsVariantPrices::class);
    $price = $finds->find($variant->id, 'USD');

    expect($price)->not->toBeNull()
        ->and($price->variant_id)->toBe($variant->id)
        ->and($price->currency)->toBe('USD')
        ->and($price->amount_minor)->toBe(1234);
});

test('retrieving price that does not exist returns null', function () {
    $variant = ProductVariant::factory()->create();

    $finds = app(FindsVariantPrices::class);
    $price = $finds->find($variant->id, 'USD');

    expect($price)->toBeNull();
});

test('unsupported currency is rejected', function () {
    $this->expectException(ValidationException::class);

    app(SetVariantPrice::class)->handle(
        1,
        'XYZ',  // unsupported currency
        1000,
    );
});

test('negative amount is rejected', function () {
    $this->expectException(ValidationException::class);

    $variant = ProductVariant::factory()->create();

    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        -100,
    );
});

test('compare-at price must be strictly greater than base price', function () {
    $this->expectException(ValidationException::class);

    $variant = ProductVariant::factory()->create();

    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1000,  // 10.00 USD
        1000,  // 10.00 USD compare-at - NOT strictly greater
    );
});

test('compare-at price equal to base price is rejected', function () {
    $this->expectException(ValidationException::class);

    $variant = ProductVariant::factory()->create();

    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1000,  // 10.00 USD
        1000,  // 10.00 USD - equal, not strictly greater
    );
});

test('update existing price for same variant/currency', function () {
    $variant = ProductVariant::factory()->create();

    // Create initial price
    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1000,
        1500,
    );

    // Update the price
    $action = app(SetVariantPrice::class);
    $price = $action->handle(
        $variant->id,
        'USD',
        2000,  // 20.00 USD
        2500,  // 25.00 USD compare-at
    );

    expect($price->amount_minor)->toBe(2000);
    expect($price->compare_at_amount_minor)->toBe(2500);
});

test('update price records old/new values in history', function () {
    $variant = ProductVariant::factory()->create();

    // Create initial price
    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1000,
        1500,
    );

    // Update the price
    $action = app(SetVariantPrice::class);
    $action->handle(
        $variant->id,
        'USD',
        2000,
        2500,
    );

    $changes = VariantPriceChange::where('variant_id', $variant->id)
        ->where('currency', 'USD')
        ->get();

    expect($changes)->toHaveCount(2);  // created + updated

    // The second change should be 'updated' with old/new values
    $updatedChange = $changes->last();
    expect($updatedChange->change_type)->toBe('updated');
    expect($updatedChange->old_amount_minor)->toBe(1000);
    expect($updatedChange->new_amount_minor)->toBe(2000);
    expect($updatedChange->old_compare_at_amount_minor)->toBe(1500);
    expect($updatedChange->new_compare_at_amount_minor)->toBe(2500);
});

test('removing a price records history and preserves it', function () {
    $variant = ProductVariant::factory()->create();

    // Create a price first
    app(SetVariantPrice::class)->handle(
        $variant->id,
        'USD',
        1000,
        1500,
    );

    $removes = app(RemovesVariantPrice::class);
    $change = $removes->handle($variant->id, 'USD');

    expect($change->change_type)->toBe('removed');
    expect($change->old_amount_minor)->toBe(1000);
    expect($change->new_amount_minor)->toBeNull();

    // History should still exist
    $history = VariantPriceChange::where('variant_id', $variant->id)
        ->where('currency', 'USD')
        ->where('change_type', 'removed')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->old_amount_minor)->toBe(1000);

    // The price should be deleted
    $price = VariantPrice::where('variant_id', $variant->id)
        ->where('currency', 'USD')
        ->first();

    expect($price)->toBeNull();
});

test('removing a price that does not exist throws', function () {
    $this->expectException(ModelNotFoundException::class);

    app(RemovesVariantPrice::class)->handle(1, 'USD');
});

test('model relationships work correctly', function () {
    $variant = ProductVariant::factory()->create();

    $price = new VariantPrice;
    $price->variant_id = $variant->id;
    $price->currency = 'USD';
    $price->amount_minor = 50000;
    $price->compare_at_amount_minor = 60000;
    $price->save();

    expect($price->variant)->toBeInstanceOf(ProductVariant::class)
        ->and($price->variant->id)->toBe($variant->id);

    $changes = $price->priceChanges;

    expect($changes)->toHaveCount(0);
});

test('prices are isolated per variant', function () {
    $product = Product::factory()->create();
    $variant1 = $product->variants()->create(['sku' => 'VAR1', 'label' => 'Variant 1']);
    $variant2 = $product->variants()->create(['sku' => 'VAR2', 'label' => 'Variant 2']);

    // Set price for variant 1
    app(SetVariantPrice::class)->handle(
        $variant1->id,
        'USD',
        1000,
        1500,
    );

    // Set price for variant 2
    app(SetVariantPrice::class)->handle(
        $variant2->id,
        'USD',
        2000,
        2500,
    );

    $price1 = VariantPrice::where('variant_id', $variant1->id)
        ->where('currency', 'USD')
        ->first();

    $price2 = VariantPrice::where('variant_id', $variant2->id)
        ->where('currency', 'USD')
        ->first();

    expect($price1->amount_minor)->toBe(1000);
    expect($price2->amount_minor)->toBe(2000);
});
