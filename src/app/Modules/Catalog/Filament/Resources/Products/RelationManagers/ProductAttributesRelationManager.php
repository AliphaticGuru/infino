<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Contracts\RemovesProductAttributes;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProductAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'productAttributes';

    protected static ?string $title = 'Attributes';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Product);

        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_variant_dimension')
                    ->label('Dimension')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('position')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('assignAttributes')
                    ->label('Assign attributes')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('attribute_ids')
                            ->label('Variant dimensions')
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->options(fn (): array => ProductAttribute::query()
                                ->where('is_active', true)
                                ->where('is_variant_dimension', true)
                                ->whereNotIn('id', $owner->productAttributes()->pluck('product_attributes.id'))
                                ->orderBy('position')
                                ->orderBy('code')
                                ->pluck('name', 'id')
                                ->all()),
                    ])
                    ->action(function (array $data, Action $action) use ($owner): void {
                        app(AssignsProductAttributes::class)->handle(
                            $owner,
                            array_map('intval', $data['attribute_ids'] ?? []),
                        );

                        $action->success();
                    })
                    ->successNotificationTitle('Attributes assigned'),
            ])
            ->recordActions([
                Action::make('removeAttribute')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ProductAttribute $record, Action $action) use ($owner): void {
                        app(RemovesProductAttributes::class)->handle($owner, [$record->getKey()]);

                        $action->success();
                    })
                    ->successNotificationTitle('Attribute removed'),
            ])
            ->defaultSort('position');
    }
}
