<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Schemas;

use App\Modules\Catalog\Support\AttributeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/')
                    ->unique('product_attributes', 'code', ignoreRecord: true)
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->helperText('Immutable machine identifier, for example "color" or "shoe_size".'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->required()
                    ->options(array_combine(
                        AttributeType::values(),
                        array_map(
                            static fn (AttributeType $type): string => ucfirst($type->value),
                            AttributeType::cases(),
                        ),
                    ))
                    ->default(AttributeType::Select->value)
                    ->disabledOn('edit')
                    ->dehydrated(),
                Toggle::make('is_variant_dimension')
                    ->label('Variant dimension')
                    ->helperText('Variant dimensions define the option combinations a variant can select.')
                    ->default(true),
                TextInput::make('position')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
