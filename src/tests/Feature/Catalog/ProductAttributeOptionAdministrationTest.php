<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateProductAttributeOption;
use App\Modules\Catalog\Actions\DeleteProductAttributeOption;
use App\Modules\Catalog\Actions\FindProductAttributeOption;
use App\Modules\Catalog\Actions\RepositionProductAttributeOptions;
use App\Modules\Catalog\Actions\UpdateProductAttributeOption;
use App\Modules\Catalog\Contracts\CreatesProductAttributeOptions;
use App\Modules\Catalog\Contracts\DeletesProductAttributeOptions;
use App\Modules\Catalog\Contracts\FindsProductAttributeOptions;
use App\Modules\Catalog\Contracts\RepositionsProductAttributeOptions;
use App\Modules\Catalog\Contracts\UpdatesProductAttributeOptions;
use App\Modules\Catalog\Data\CreateProductAttributeOptionData;
use App\Modules\Catalog\Data\UpdateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use Illuminate\Validation\ValidationException;

test('option contracts resolve to their actions', function () {
    expect(app(FindsProductAttributeOptions::class))->toBeInstanceOf(FindProductAttributeOption::class)
        ->and(app(CreatesProductAttributeOptions::class))->toBeInstanceOf(CreateProductAttributeOption::class)
        ->and(app(UpdatesProductAttributeOptions::class))->toBeInstanceOf(UpdateProductAttributeOption::class)
        ->and(app(DeletesProductAttributeOptions::class))->toBeInstanceOf(DeleteProductAttributeOption::class)
        ->and(app(RepositionsProductAttributeOptions::class))->toBeInstanceOf(RepositionProductAttributeOptions::class);
});

test('an option can be created under an attribute', function () {
    $attribute = ProductAttribute::factory()->create();

    $option = app(CreatesProductAttributeOptions::class)->handle(
        $attribute,
        new CreateProductAttributeOptionData(
            code: 'red',
            label: 'Red',
            position: 3,
        ),
    );

    expect($option->product_attribute_id)->toBe($attribute->id)
        ->and($option->code)->toBe('red')
        ->and($option->label)->toBe('Red')
        ->and($option->position)->toBe(3)
        ->and($option->is_active)->toBeTrue();
});

test('option codes are unique per attribute but reusable across attributes', function () {
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);

    app(CreatesProductAttributeOptions::class)->handle($color, new CreateProductAttributeOptionData('small', 'Small'));
    app(CreatesProductAttributeOptions::class)->handle($size, new CreateProductAttributeOptionData('small', 'Small'));

    expect(fn () => app(CreatesProductAttributeOptions::class)->handle(
        $color,
        new CreateProductAttributeOptionData('small', 'Small again'),
    ))->toThrow(ValidationException::class);
});

test('option codes must be machine safe', function (string $code) {
    $attribute = ProductAttribute::factory()->create();

    expect(fn () => app(CreatesProductAttributeOptions::class)->handle(
        $attribute,
        new CreateProductAttributeOptionData($code, 'Invalid'),
    ))->toThrow(ValidationException::class);
})->with([
    'uppercase' => ['Red'],
    'space' => ['dark red'],
    'colon' => ['red:blue'],
]);

test('option updates change editable fields but never the code', function () {
    $option = ProductAttributeOption::factory()->create([
        'code' => 'red',
        'label' => 'Red',
        'position' => 0,
    ]);

    $updated = app(UpdatesProductAttributeOptions::class)->handle(
        $option,
        new UpdateProductAttributeOptionData(
            label: 'Crimson',
            position: 4,
            isActive: false,
        ),
    );

    expect($updated->code)->toBe('red')
        ->and($updated->label)->toBe('Crimson')
        ->and($updated->position)->toBe(4)
        ->and($updated->is_active)->toBeFalse();
});

test('an unreferenced option can be deleted', function () {
    $option = ProductAttributeOption::factory()->create();

    app(DeletesProductAttributeOptions::class)->handle($option);

    $this->assertDatabaseMissing('product_attribute_options', ['id' => $option->id]);
});

test('an option selected by a variant cannot be deleted', function () {
    $option = ProductAttributeOption::factory()->create();
    $variant = ProductVariant::factory()->create();
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    expect(fn () => app(DeletesProductAttributeOptions::class)->handle($option))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('product_attribute_options', ['id' => $option->id]);
});

test('options can be retrieved for their attribute in position order', function () {
    $attribute = ProductAttribute::factory()->create();
    $other = ProductAttribute::factory()->create();
    $second = ProductAttributeOption::factory()->forAttribute($attribute)->create(['code' => 'medium', 'position' => 2]);
    $first = ProductAttributeOption::factory()->forAttribute($attribute)->create(['code' => 'large', 'position' => 1]);
    ProductAttributeOption::factory()->forAttribute($other)->create();

    $finder = app(FindsProductAttributeOptions::class);

    expect($finder->find($first->id)->is($first))->toBeTrue()
        ->and($finder->forAttribute($attribute)->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('options can be repositioned within their attribute', function () {
    $attribute = ProductAttribute::factory()->create();
    $first = ProductAttributeOption::factory()->forAttribute($attribute)->create(['position' => 0]);
    $second = ProductAttributeOption::factory()->forAttribute($attribute)->create(['position' => 1]);

    $options = app(RepositionsProductAttributeOptions::class)->handle(
        $attribute,
        [$second->id, $first->id],
    );

    expect($options->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(0);
});

test('repositioning requires every option of the attribute exactly once', function () {
    $attribute = ProductAttribute::factory()->create();
    $first = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $second = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    expect(fn () => app(RepositionsProductAttributeOptions::class)->handle($attribute, [$first->id]))
        ->toThrow(ValidationException::class);

    expect(fn () => app(RepositionsProductAttributeOptions::class)->handle($attribute, [$first->id, $first->id]))
        ->toThrow(ValidationException::class);

    expect($second->fresh()->position)->toBe(0);
});
