<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Schemas;

use App\Modules\Catalog\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('parent_id')
                    ->label('Parent category')
                    ->searchable()
                    ->placeholder('None')
                    ->options(fn (?Category $record): array => Category::query()
                        ->when(
                            $record,
                            fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
