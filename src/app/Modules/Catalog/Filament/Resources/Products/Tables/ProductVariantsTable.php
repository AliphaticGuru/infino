<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Contracts\RepositionsProductVariants;
use App\Modules\Catalog\Contracts\TransitionsProductVariantStatus;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Data\UpdateProductVariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class ProductVariantsTable
{
    public static function configure(Table $table, Product $product): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('variant_key')
                    ->label('Variant key')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('position')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data) use ($product): ProductVariant {
                        return app(CreatesProductVariants::class)->handle(
                            $product,
                            new CreateProductVariantData(
                                sku: $data['sku'] ?? null,
                                label: $data['label'] ?? null,
                                variantKey: $data['variant_key'] ?? null,
                                attributeOptions: self::attributeOptions($product, $data),
                            ),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProductVariant $record, array $data) use ($product): ProductVariant {
                        return app(UpdatesProductVariants::class)->handle(
                            $record,
                            new UpdateProductVariantData(
                                sku: $data['sku'] ?? null,
                                label: $data['label'] ?? null,
                                variantKey: $data['variant_key'] ?? null,
                                attributeOptions: self::attributeOptions($product, $data),
                            ),
                        );
                    }),
                Action::make('activate')
                    ->authorize('activate')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (ProductVariant $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->successNotificationTitle('Variant activated')
                    ->action(function (ProductVariant $record, Action $action): void {
                        app(TransitionsProductVariantStatus::class)->activate($record);

                        $action->success();
                    }),
                Action::make('deactivate')
                    ->authorize('deactivate')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->visible(fn (ProductVariant $record): bool => $record->is_active
                        && ! $record->isDefault()
                        && $record->product->variants()
                            ->where('is_active', true)
                            ->whereKeyNot($record->getKey())
                            ->exists())
                    ->requiresConfirmation()
                    ->successNotificationTitle('Variant deactivated')
                    ->action(function (ProductVariant $record, Action $action): void {
                        app(TransitionsProductVariantStatus::class)->deactivate($record);

                        $action->success();
                    }),
            ])
            ->reorderable('position')
            ->authorizeReorder(fn (): bool => Gate::allows('reorder', ProductVariant::class))
            ->afterReordering(function (array $order) use ($product): void {
                app(RepositionsProductVariants::class)->handle($product, $order);
            })
            ->defaultSort('position');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>|null
     */
    private static function attributeOptions(Product $product, array $data): ?array
    {
        $dimensionIds = $product->productAttributes()
            ->where('product_attributes.is_variant_dimension', true)
            ->pluck('product_attributes.id')
            ->all();

        if ($dimensionIds === []) {
            return null;
        }

        $raw = $data['attribute_options'] ?? [];
        $options = [];

        foreach ($dimensionIds as $attributeId) {
            $value = $raw[$attributeId] ?? null;

            if ($value !== null && $value !== '') {
                $options[(int) $attributeId] = (int) $value;
            }
        }

        return $options;
    }
}
