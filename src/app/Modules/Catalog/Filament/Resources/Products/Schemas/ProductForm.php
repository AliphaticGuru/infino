<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use App\Modules\Catalog\Models\Product;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sku')
                    ->label('Default variant SKU')
                    ->maxLength(100)
                    ->unique('product_variants', 'sku', ignoreRecord: true)
                    ->helperText('Leave blank to generate a SKU from the product ID.')
                    ->hiddenOn('edit'),
                TextInput::make('default_variant_sku')
                    ->label('Default variant SKU')
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create')
                    ->default(fn (?Product $record): ?string => $record?->defaultVariant?->sku),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->hiddenOn('create'),
            ]);
    }
}
