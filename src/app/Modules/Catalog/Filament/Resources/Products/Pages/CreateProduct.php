<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatesProducts::class)->handle(
            new CreateProductData(
                name: $data['name'],
                description: $data['description'] ?? null,
                sku: $data['sku'] ?? null,
            ),
        );
    }
}
