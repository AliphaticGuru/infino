<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Contracts\SyncsProductCategories;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Data\UpdateProductData;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        return app(UpdatesProducts::class)->handle(
            $record,
            new UpdateProductData(
                name: $data['name'] ?? null,
                description: $data['description'] ?? null,
                isActive: $data['is_active'] ?? null,
            ),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->authorize('publish')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (Product $record): bool => $record->status === ProductStatus::Draft)
                ->requiresConfirmation()
                ->successNotificationTitle('Product published')
                ->action(function (Product $record, Action $action): void {
                    app(TransitionsProductStatus::class)->publish($record);
                    $record->refresh();

                    $action->success();
                }),
            Action::make('unpublish')
                ->authorize('publish')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (Product $record): bool => $record->status === ProductStatus::Published)
                ->requiresConfirmation()
                ->successNotificationTitle('Product unpublished')
                ->action(function (Product $record, Action $action): void {
                    app(TransitionsProductStatus::class)->unpublish($record);
                    $record->refresh();

                    $action->success();
                }),
            Action::make('archive')
                ->authorize('publish')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn (Product $record): bool => in_array($record->status, [ProductStatus::Draft, ProductStatus::Published], true))
                ->requiresConfirmation()
                ->successNotificationTitle('Product archived')
                ->action(function (Product $record, Action $action): void {
                    app(TransitionsProductStatus::class)->archive($record);
                    $record->refresh();

                    $action->success();
                }),
            Action::make('restore')
                ->authorize('publish')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (Product $record): bool => $record->status === ProductStatus::Archived)
                ->requiresConfirmation()
                ->successNotificationTitle('Product restored')
                ->action(function (Product $record, Action $action): void {
                    app(TransitionsProductStatus::class)->restore($record);
                    $record->refresh();

                    $action->success();
                }),
            Action::make('manageCategories')
                ->authorize('manageCategories')
                ->icon(Heroicon::OutlinedTag)
                ->successNotificationTitle('Categories updated')
                ->schema([
                    Select::make('category_ids')
                        ->label('Categories')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => Category::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (Product $record): array => $record->categories()
                            ->pluck('categories.id')
                            ->all()),
                ])
                ->action(function (Product $record, array $data, Action $action): void {
                    app(SyncsProductCategories::class)->handle(
                        $record,
                        $data['category_ids'] ?? [],
                    );

                    $record->refresh();

                    $action->success();
                }),
        ];
    }
}
