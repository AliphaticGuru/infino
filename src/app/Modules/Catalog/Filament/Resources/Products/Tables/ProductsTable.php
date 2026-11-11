<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Support\ProductStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus|string $state): string => $state instanceof ProductStatus
                        ? $state->name
                        : ucfirst((string) $state))
                    ->sortable(),
                TextColumn::make('categories.name')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),
                TextColumn::make('variants.sku')
                    ->label('SKUs')
                    ->limitList(3)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
