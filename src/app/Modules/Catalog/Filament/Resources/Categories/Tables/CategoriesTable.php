<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Tables;

use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class CategoriesTable
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
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Children'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('parent_id')
                    ->label('Parent category')
                    ->options(fn (): array => Category::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (Category $record): bool {
                        app(DeletesCategories::class)->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('name');
    }
}
