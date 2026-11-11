<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Data\CreateCategoryData;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatesCategories::class)->handle(
            new CreateCategoryData(
                name: $data['name'],
                description: $data['description'] ?? null,
                parentId: $data['parent_id'] ?? null,
                isActive: $data['is_active'] ?? true,
            ),
        );
    }
}
