<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(100)
                    ->unique('product_variants', 'sku', ignoreRecord: true),
                TextInput::make('label')
                    ->maxLength(255)
                    ->helperText('Display name, for example "Red / M".'),
                TextInput::make('variant_key')
                    ->label('Variant key')
                    ->maxLength(255)
                    ->helperText('Optional deterministic option signature, unique per product.'),
            ]);
    }
}
