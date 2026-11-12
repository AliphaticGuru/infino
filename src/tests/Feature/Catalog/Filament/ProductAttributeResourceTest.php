<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Support\AttributeType;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the attribute resource lists attributes', function () {
    $attributes = ProductAttribute::factory()->count(3)->create();

    Livewire::test(ListAttributes::class)
        ->assertCanSeeTableRecords($attributes);
});

test('an attribute can be created through the attribute resource', function () {
    Livewire::test(CreateAttribute::class)
        ->fillForm([
            'code' => 'color',
            'name' => 'Color',
            'type' => AttributeType::Select->value,
            'is_variant_dimension' => true,
            'position' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('product_attributes', [
        'code' => 'color',
        'name' => 'Color',
        'type' => AttributeType::Select->value,
        'is_variant_dimension' => true,
        'position' => 3,
        'is_active' => true,
    ]);
});

test('attribute creation validates unique and machine-safe codes', function () {
    ProductAttribute::factory()->create(['code' => 'color']);

    Livewire::test(CreateAttribute::class)
        ->fillForm([
            'code' => 'color',
            'name' => 'Duplicate',
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);

    Livewire::test(CreateAttribute::class)
        ->fillForm([
            'code' => 'Not Safe',
            'name' => 'Invalid',
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);

    $this->assertDatabaseCount('product_attributes', 1);
});

test('an attribute can be edited without changing its code', function () {
    $attribute = ProductAttribute::factory()->create([
        'code' => 'color',
        'name' => 'Color',
        'position' => 0,
    ]);

    Livewire::test(EditAttribute::class, ['record' => $attribute->getRouteKey()])
        ->fillForm([
            'name' => 'Colour',
            'position' => 4,
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $attribute->refresh();

    expect($attribute->code)->toBe('color')
        ->and($attribute->name)->toBe('Colour')
        ->and($attribute->position)->toBe(4)
        ->and($attribute->is_active)->toBeFalse();
});

test('an unreferenced attribute can be deleted through the table action', function () {
    $attribute = ProductAttribute::factory()->create();

    Livewire::test(ListAttributes::class)
        ->callTableAction('delete', $attribute);

    $this->assertDatabaseMissing('product_attributes', ['id' => $attribute->id]);
});

test('an assigned attribute cannot be deleted through the table action', function () {
    $attribute = ProductAttribute::factory()->create();
    $product = Product::factory()->withoutVariant()->create();
    $product->productAttributes()->attach($attribute);

    Livewire::test(ListAttributes::class)
        ->callTableAction('delete', $attribute);

    $this->assertDatabaseHas('product_attributes', ['id' => $attribute->id]);
});

test('options can be created and edited through the options relation manager', function () {
    $attribute = ProductAttribute::factory()->create();

    $manager = Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $attribute,
        'pageClass' => EditAttribute::class,
    ]);

    $manager
        ->callTableAction('create', data: [
            'code' => 'red',
            'label' => 'Red',
            'position' => 1,
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    $option = $attribute->options()->where('code', 'red')->sole();

    $manager
        ->callTableAction('edit', $option, data: [
            'label' => 'Crimson',
            'position' => 2,
            'is_active' => false,
        ])
        ->assertHasNoTableActionErrors();

    $option->refresh();

    expect($option->code)->toBe('red')
        ->and($option->label)->toBe('Crimson')
        ->and($option->position)->toBe(2)
        ->and($option->is_active)->toBeFalse();
});

test('option codes are unique within an attribute in the relation manager', function () {
    $attribute = ProductAttribute::factory()->create();
    ProductAttributeOption::factory()->forAttribute($attribute)->create(['code' => 'red']);

    Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $attribute,
        'pageClass' => EditAttribute::class,
    ])
        ->callTableAction('create', data: [
            'code' => 'red',
            'label' => 'Duplicate',
        ])
        ->assertHasTableActionErrors(['code']);

    expect($attribute->options()->count())->toBe(1);
});

test('the same option code can be used by different attributes', function () {
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'small']);

    Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $size,
        'pageClass' => EditAttribute::class,
    ])
        ->callTableAction('create', data: [
            'code' => 'small',
            'label' => 'Small',
        ])
        ->assertHasNoTableActionErrors();

    expect($size->options()->where('code', 'small')->exists())->toBeTrue();
});

test('an unreferenced option can be deleted through the relation manager', function () {
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $attribute,
        'pageClass' => EditAttribute::class,
    ])
        ->callTableAction('delete', $option);

    $this->assertDatabaseMissing('product_attribute_options', ['id' => $option->id]);
});

test('options can be reordered through the relation manager', function () {
    $attribute = ProductAttribute::factory()->create();
    $first = ProductAttributeOption::factory()->forAttribute($attribute)->create(['code' => 'first', 'position' => 0]);
    $second = ProductAttributeOption::factory()->forAttribute($attribute)->create(['code' => 'second', 'position' => 1]);

    Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $attribute,
        'pageClass' => EditAttribute::class,
    ])
        ->call('reorderTable', [$second->id, $first->id]);

    expect($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(0);
});

test('non-admin users cannot mount the attribute resource pages', function () {
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ListAttributes::class)->assertForbidden();
});

test('attribute policies deny every ability to non-admin users and allow admins', function () {
    $nonAdmin = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    $classAbilities = ['viewAny', 'create', 'deleteAny'];
    $modelAbilities = ['view', 'update', 'delete'];

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($nonAdmin)->allows($ability, ProductAttribute::class))->toBeFalse()
            ->and(Gate::forUser($admin)->allows($ability, ProductAttribute::class))->toBeTrue();
    }

    foreach ($modelAbilities as $ability) {
        expect(Gate::forUser($nonAdmin)->allows($ability, $attribute))->toBeFalse()
            ->and(Gate::forUser($admin)->allows($ability, $attribute))->toBeTrue()
            ->and(Gate::forUser($nonAdmin)->allows($ability, $option))->toBeFalse()
            ->and(Gate::forUser($admin)->allows($ability, $option))->toBeTrue();
    }
});

test('product policies expose attribute management to admins only', function () {
    $nonAdmin = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();

    expect(Gate::forUser($nonAdmin)->allows('manageAttributes', $product))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('manageAttributes', $product))->toBeTrue();
});
