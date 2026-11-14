<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Data\UpdateCategoryData;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Category $record */
        return app(UpdatesCategories::class)->handle(
            $record,
            new UpdateCategoryData(
                name: $data['name'],
                description: $data['description'] ?? null,
                parentId: $data['parent_id'] ?? null,
                isActive: $data['is_active'] ?? null,
            ),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Category $record): bool {
                    app(DeletesCategories::class)->handle($record);

                    return true;
                }),
        ];
    }
}
