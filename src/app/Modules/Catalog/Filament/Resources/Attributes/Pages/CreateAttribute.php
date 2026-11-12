<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Pages;

use App\Modules\Catalog\Contracts\CreatesProductAttributes;
use App\Modules\Catalog\Data\CreateProductAttributeData;
use App\Modules\Catalog\Filament\Resources\Attributes\AttributeResource;
use App\Modules\Catalog\Support\AttributeType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatesProductAttributes::class)->handle(
            new CreateProductAttributeData(
                code: $data['code'],
                name: $data['name'],
                type: AttributeType::tryFrom($data['type'] ?? '') ?? AttributeType::Select,
                isVariantDimension: $data['is_variant_dimension'] ?? true,
                position: (int) ($data['position'] ?? 0),
                isActive: $data['is_active'] ?? true,
            ),
        );
    }
}
