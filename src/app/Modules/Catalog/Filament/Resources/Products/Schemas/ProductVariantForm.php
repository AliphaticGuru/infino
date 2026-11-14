<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductVariant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ProductVariantForm
{
    public static function configure(Schema $schema, ?Product $product = null): Schema
    {
        $components = [
            TextInput::make('sku')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->maxLength(100)
                ->unique('product_variants', 'sku', ignoreRecord: true),
            TextInput::make('label')
                ->maxLength(255)
                ->helperText('Display name, for example "Red / M".'),
        ];

        $dimensions = $product === null
            ? collect()
            : $product->productAttributes()
                ->where('product_attributes.is_variant_dimension', true)
                ->orderBy('product_attributes.position')
                ->orderBy('product_attributes.code')
                ->get();

        if ($dimensions->isEmpty()) {
            $components[] = TextInput::make('variant_key')
                ->label('Variant key')
                ->maxLength(255)
                ->helperText('Optional deterministic option signature, unique per product.');

            return $schema->components($components);
        }

        foreach ($dimensions as $attribute) {
            assert($attribute instanceof ProductAttribute);

            $components[] = Select::make('attribute_options.'.$attribute->getKey())
                ->label($attribute->name)
                ->options(function (?ProductVariant $record) use ($attribute): array {
                    $options = $attribute->options()
                        ->where('is_active', true)
                        ->orderBy('position')
                        ->orderBy('code')
                        ->pluck('label', 'id')
                        ->all();

                    if ($record !== null) {
                        $current = $record->attributeOptions()
                            ->where('product_attribute_id', $attribute->getKey())
                            ->first();

                        if (
                            $current !== null
                            && ! array_key_exists($current->product_attribute_option_id, $options)
                        ) {
                            $option = $current->option;

                            if ($option !== null) {
                                $options[$option->getKey()] = $option->label.' (inactive)';
                            }
                        }
                    }

                    return $options;
                })
                ->default(fn (?ProductVariant $record): ?int => $record?->attributeOptions()
                    ->where('product_attribute_id', $attribute->getKey())
                    ->value('product_attribute_option_id'))
                ->required()
                ->native(false)
                ->helperText('Required for configured variants. The variant key is derived automatically.');
        }

        return $schema->components($components);
    }
}
