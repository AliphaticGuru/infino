<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Tables;

use App\Modules\Catalog\Contracts\DeletesProductAttributes;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Support\AttributeType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => ucfirst($state->value)),
                IconColumn::make('is_variant_dimension')
                    ->label('Dimension')
                    ->boolean(),
                TextColumn::make('position')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('options_count')
                    ->counts('options')
                    ->label('Options'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_variant_dimension')
                    ->label('Variant dimension'),
                SelectFilter::make('type')
                    ->options(array_combine(
                        AttributeType::values(),
                        array_map(
                            static fn (AttributeType $type): string => ucfirst($type->value),
                            AttributeType::cases(),
                        ),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (ProductAttribute $record): bool {
                        app(DeletesProductAttributes::class)->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('position');
    }
}
