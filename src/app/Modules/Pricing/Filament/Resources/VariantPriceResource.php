<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources;

use App\Modules\Pricing\Filament\Resources\VariantPrice\Pages\CreateVariantPrice;
use App\Modules\Pricing\Filament\Resources\VariantPrice\Pages\EditVariantPrice;
use App\Modules\Pricing\Filament\Resources\VariantPrice\Pages\ListVariantPrices;
use App\Modules\Pricing\Filament\Resources\VariantPrice\Tables\VariantPricesTable;
use App\Modules\Pricing\Policies\VariantPricePolicy;
use App\Modules\Pricing\Models\VariantPrice;
use Filament\Forms\Components\Select;
use Filament\Heroicon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\UnitEnum;
use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Infolists\Infolist;
use Filament\Infolists\Columns\IconColumn as InfolistIconColumn;
use Filament\Infolists\Columns\TextColumn as InfolistTextColumn;

final class VariantPriceResource extends Resource
{
    protected static ?string $model = VariantPrice::class;

    protected static ?string $recordTitleAttribute = 'currency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Variant Prices';

    public static function form(Schema $schema): Schema
    {
        $components = [];

        $components[] = Select::make('variant_id')
            ->label('Variant')
            ->options(['1' => 'Variant 1', '2' => 'Variant 2'])
            ->searchable()
            ->required();

        $components[] = Select::make('currency')
            ->label('Currency')
            ->options([
                'NGN' => 'NGN',
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
            ])
            ->default('NGN')
            ->searchable()
            ->required();

        $components[] = TextInput::make('amount_minor')
            ->label('Amount (minor units)')
            ->numeric()
            ->rules(['integer', 'min:0'])
            ->prefix(function (): string {
                return app('money')->format(0, 'NGN');
            })
            ->suffix(' minor');

        $components[] = TextInput::make('compare_at_amount_minor')
            ->label('Compare-at amount (minor units)')
            ->numeric()
            ->rules(['integer', 'nullable'])
            ->hiddenOn('edit')
            ->dehydrated();

        $components[] = Select::make('changed_by')
            ->label('Changed by')
            ->options(\App\Modules\Identity\Models\User::where('is_admin', true)
                ->pluck('name', 'id')
                ->toArray())
            ->searchable()
            ->nullable();

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        return VariantPricesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariantPrices::route('/'),
            'create' => CreateVariantPrice::route('/create'),
            'edit' => EditVariantPrice::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getInfolist(): Infolist
    {
        return Infolist::make()
            ->schema([
                InfolistTextColumn::make('variant.label')
                    ->label('Variant'),

                InfolistSelectColumn::make('currency')
                    ->label('Currency')
                    ->options([
                        'NGN' => 'NGN',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                    ]),

                InfolistTextColumn::make('amount_minor')
                    ->label('Amount')
                    ->money(currency: 'USD')
                    ->formatStateUsing(fn (?string $state, ?VariantPrice $record): string => $record ? Money::format($record->amount_minor, $record->currency) : '-'),

                InfolistTextColumn::make('compare_at_amount_minor')
                    ->label('Compare-at')
                    ->money(currency: 'USD')
                    ->formatStateUsing(fn (?string $state, ?VariantPrice $record): string => $record ? Money::format($record->compare_at_amount_minor, $record->currency) : '-'),

                InfolistTextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime('m/d/Y H:i'),
            ]);
    }
}
