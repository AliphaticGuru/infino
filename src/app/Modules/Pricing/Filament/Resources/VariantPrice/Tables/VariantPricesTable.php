<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\VariantPrice\Tables;

use App\Modules\Pricing\Actions\FindVariantPrices;
use App\Modules\Pricing\Actions\RemoveVariantPrice;
use App\Modules\Pricing\Actions\SetVariantPrice;
use App\Modules\Pricing\Contracts\FindsVariantPrices;
use App\Modules\Pricing\Contracts\RemovesVariantPrice;
use App\Modules\Pricing\Contracts\SetsVariantPrice;
use App\Modules\Pricing\Data\SetVariantPriceData;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Support\Money;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\FilterGroup;
use Filament\Tables\Filters\Filter;
use Filament\Infolists\Infolist;
use Filament\Infolists\Columns\IconColumn as InfolistIconColumn;
use Filament\Infolists\Columns\TextColumn as InfolistTextColumn;

final class VariantPricesTable extends Table
{
    public static function make(?\Filament\Tables\Contracts\HasTable $livewire = null): static
    {
        return new static(
            query: VariantPrice::query()->with('variant'),
        );
    }

    protected function configureBuilder(Builder $builder): Builder
    {
        return $builder->defaultOrderBy('currency', 'asc')
            ->defaultOrderBy('created_at', 'desc');
    }

    public function getColumns(): array
    {
        return [
            TextColumn::make('variant.label')
                ->label('Variant')
                ->searchable()
                ->toggleable(),

            SelectColumn::make('currency')
                ->label('Currency')
                ->options([
                    'NGN' => 'NGN',
                    'USD' => 'USD',
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                ])
                ->searchable()
                ->toggleable(),

            TextColumn::make('amount_minor')
                ->label('Amount')
                ->money(currency: 'USD')
                ->formatStateUsing(fn (?string $state, ?\App\Modules\Pricing\Models\VariantPrice $record): string => $record ? Money::format($record->amount_minor, $record->currency) : '-')
                ->visible(false)
                ->toggleable(),

            TextColumn::make('compare_at_amount_minor')
                ->label('Compare-at')
                ->money(currency: 'USD')
                ->formatStateUsing(fn (?string $state, ?\App\Modules\Pricing\Models\VariantPrice $record): string => $record ? Money::format($record->compare_at_amount_minor, $record->currency) : '-')
                ->toggleable(),

            IconColumn::make('priceChanges')
                ->label('History')
                ->icon('heroicon-m-history')
                ->tooltip('View price change history')
                ->active(),

            Columns\ActionsColumn::make()
                ->selectable()
                ->menuItems([
                    EditAction::make()
                        ->hidden(fn (?\App\Modules\Pricing\Models\VariantPrice $record): bool => !$record || Gate::allows('pricing-edit', $record)),

                    DeleteAction::make()
                        ->hidden(fn (?\App\Modules\Pricing\Models\VariantPrice $record): bool => !$record || Gate::allows('pricing-delete', $record))
                        ->action(function (?\App\Modules\Pricing\Models\VariantPrice $record): void
                        {
                            if ($record) {
                                $removes = app(RemovesVariantPrice::class);
                                $removes->handle($record->variant_id, $record->currency);
                            }
                        }),
                ]),
        ];
    }

    public function getFilters(bool $withHidden = false): array
    {
        return [
            SelectFilter::make('currency')
                ->label('Currency')
                ->options([
                    'NGN' => 'NGN',
                    'USD' => 'USD',
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                ])
                ->applyStateFilter(fn (?string $state, ?Builder $builder): Builder => $state
                    ? $builder->where('currency', $state)
                    : $builder),
        ];
    }

    public function getInfolist(): \Filament\Infolists\Infolist
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
                    ->formatStateUsing(fn (?string $state, ?\App\Modules\Pricing\Models\VariantPrice $record): string => $record ? Money::format($record->amount_minor, $record->currency) : '-'),

                InfolistTextColumn::make('compare_at_amount_minor')
                    ->label('Compare-at')
                    ->money(currency: 'USD')
                    ->formatStateUsing(fn (?string $state, ?\App\Modules\Pricing\Models\VariantPrice $record): string => $record ? Money::format($record->compare_at_amount_minor, $record->currency) : '-'),

                InfolistTextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime('m/d/Y H:i'),
            ]);
    }
}