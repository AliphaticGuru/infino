<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function mediaRelationManager(Product $product): Testable
{
    return Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

beforeEach(function () {
    Storage::fake('public');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the media relation manager lists only the product gallery by default', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productMedia = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $variantMedia = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);

    mediaRelationManager($product)
        ->assertCanSeeTableRecords([$productMedia])
        ->assertCanNotSeeTableRecords([$variantMedia]);
});

test('the variant tab scopes the table to that variant gallery', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productMedia = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $variantMedia = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);

    mediaRelationManager($product)
        ->set('activeTab', 'variant_'.$variant->id)
        ->assertCanSeeTableRecords([$variantMedia])
        ->assertCanNotSeeTableRecords([$productMedia]);
});

test('an image can be uploaded through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();

    mediaRelationManager($product)
        ->callTableAction('uploadImage', data: [
            'file' => mediaUpload(),
            'alt_text' => 'Uploaded image',
            'is_primary' => false,
        ])
        ->assertHasNoTableActionErrors();

    $media = ProductMedia::query()->sole();

    expect($media->product_id)->toBe($product->id)
        ->and($media->product_variant_id)->toBeNull()
        ->and($media->alt_text)->toBe('Uploaded image')
        ->and($media->is_primary)->toBeTrue();

    Storage::disk('public')->assertExists($media->path);
});

test('an image can be uploaded to a variant gallery through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    mediaRelationManager($product)
        ->callTableAction('uploadImage', data: [
            'file' => mediaUpload('variant.png'),
            'product_variant_id' => $variant->id,
        ])
        ->assertHasNoTableActionErrors();

    $media = ProductMedia::query()->sole();

    expect($media->product_variant_id)->toBe($variant->id);
});

test('upload validation errors are surfaced', function () {
    $product = Product::factory()->withoutVariant()->create();

    mediaRelationManager($product)
        ->callTableAction('uploadImage', data: [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'not an image'),
        ])
        ->assertHasTableActionErrors(['file']);

    expect(ProductMedia::query()->count())->toBe(0);
});

test('alt text can be edited through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = ProductMedia::factory()->forProduct($product)->create(['alt_text' => 'Old']);

    mediaRelationManager($product)
        ->callTableAction('editAltText', $media, data: [
            'alt_text' => 'New alt text',
        ])
        ->assertHasNoTableActionErrors();

    expect($media->fresh()->alt_text)->toBe('New alt text');
});

test('a media row can be promoted to primary through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    mediaRelationManager($product)
        ->callTableAction('setPrimary', $second)
        ->assertHasNoTableActionErrors();

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse();
});

test('a media row can be deleted through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);

    mediaRelationManager($product)
        ->callTableAction('delete', $media)
        ->assertHasNoTableActionErrors();

    expect(ProductMedia::query()->whereKey($media->id)->exists())->toBeFalse();
});

test('media can be moved up and down through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);
    $third = ProductMedia::factory()->forProduct($product)->create(['position' => 2]);

    mediaRelationManager($product)
        ->callTableAction('moveDown', $first)
        ->assertHasNoTableActionErrors();

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($third->fresh()->position)->toBe(2);

    mediaRelationManager($product)
        ->callTableAction('moveUp', $third)
        ->assertHasNoTableActionErrors();

    expect($second->fresh()->position)->toBe(0)
        ->and($third->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2);
});

test('non-admin users cannot view the media relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();

    $this->actingAs(User::factory()->create());

    expect(MediaRelationManager::canViewForRecord($product, EditProduct::class))->toBeFalse();
});

test('admin users can view the media relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();

    expect(MediaRelationManager::canViewForRecord($product, EditProduct::class))->toBeTrue();
});

test('non-admin users cannot access the product edit page', function () {
    $product = Product::factory()->withoutVariant()->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/products/'.$product->id.'/edit')
        ->assertForbidden();
});
