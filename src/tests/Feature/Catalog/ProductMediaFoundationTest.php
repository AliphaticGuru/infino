<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\MediaStorage;
use App\Modules\Catalog\Database\Factories\ProductMediaFactory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\FilesystemMediaStorage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('product media table has the expected schema', function () {
    expect(Schema::hasTable('product_media'))->toBeTrue()
        ->and(Schema::hasColumns('product_media', [
            'id',
            'product_id',
            'product_variant_id',
            'disk',
            'path',
            'original_name',
            'mime_type',
            'size',
            'width',
            'height',
            'alt_text',
            'position',
            'is_primary',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('product media table has the expected indexes', function () {
    $indexes = collect(Schema::getIndexes('product_media'))
        ->pluck('name')
        ->all();

    expect($indexes)
        ->toContain('product_media_product_id_position_index')
        ->toContain('product_media_product_variant_id_position_index')
        ->toContain('product_media_product_id_is_primary_index');
});

test('product media table has the expected foreign keys', function () {
    $foreignKeys = collect(Schema::getForeignKeys('product_media'));

    $productKey = $foreignKeys->first(
        fn (array $key): bool => $key['columns'] === ['product_id']
            && $key['foreign_table'] === 'products',
    );

    $variantKey = $foreignKeys->first(
        fn (array $key): bool => $key['columns'] === ['product_variant_id', 'product_id']
            && $key['foreign_table'] === 'product_variants',
    );

    expect($productKey)->not->toBeNull()
        ->and($variantKey)->not->toBeNull();
});

test('product media factory creates a persisted product gallery image', function () {
    $factory = ProductMedia::factory();

    expect($factory)->toBeInstanceOf(ProductMediaFactory::class);

    $media = $factory->forProduct(Product::factory()->withoutVariant()->create())->create();

    expect($media->exists)->toBeTrue()
        ->and($media->product)->toBeInstanceOf(Product::class)
        ->and($media->product_variant_id)->toBeNull()
        ->and($media->disk)->toBe('public')
        ->and($media->is_primary)->toBeFalse();
});

test('product media factory creates a persisted variant gallery image', function () {
    $variant = ProductVariant::factory()->create();

    $media = ProductMedia::factory()->forVariant($variant)->create();

    expect($media->exists)->toBeTrue()
        ->and($media->product_id)->toBe($variant->product_id)
        ->and($media->productVariant)->toBeInstanceOf(ProductVariant::class)
        ->and($media->productVariant->is($variant))->toBeTrue();
});

test('product and variant media relationships are gallery scoped and ordered', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);
    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $variantMedia = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);

    expect($product->media()->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($variant->media()->pluck('id')->all())->toBe([$variantMedia->id])
        ->and($product->allMedia()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id, $variantMedia->id])->sort()->values()->all());
});

test('deleting a product cascades both galleries of media', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    ProductMedia::factory()->forProduct($product)->create();
    ProductMedia::factory()->forVariant($variant)->create();

    expect(ProductMedia::query()->count())->toBe(2);

    $product->delete();

    expect(ProductMedia::query()->count())->toBe(0);
});

test('deleting a variant cascades only its gallery', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productMedia = ProductMedia::factory()->forProduct($product)->create();
    ProductMedia::factory()->forVariant($variant)->create();

    $variant->delete();

    expect(ProductMedia::query()->pluck('id')->all())->toBe([$productMedia->id]);
});

test('the composite foreign key rejects a variant owned by another product', function () {
    $product = Product::factory()->withoutVariant()->create();
    $otherVariant = ProductVariant::factory()->create();

    expect(fn () => ProductMedia::factory()->create([
        'product_id' => $product->id,
        'product_variant_id' => $otherVariant->id,
    ]))->toThrow(QueryException::class);
});

test('the media storage contract is bound to the filesystem implementation', function () {
    expect(app(MediaStorage::class))->toBeInstanceOf(FilesystemMediaStorage::class);
});
