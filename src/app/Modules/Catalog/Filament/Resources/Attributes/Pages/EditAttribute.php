<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Pages;

use App\Modules\Catalog\Contracts\DeletesProductAttributes;
use App\Modules\Catalog\Contracts\UpdatesProductAttributes;
use App\Modules\Catalog\Data\UpdateProductAttributeData;
use App\Modules\Catalog\Filament\Resources\Attributes\AttributeResource;
use App\Modules\Catalog\Models\ProductAttribute;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductAttribute $record */
        return app(UpdatesProductAttributes::class)->handle(
            $record,
            new UpdateProductAttributeData(
                name: $data['name'] ?? null,
                isVariantDimension: $data['is_variant_dimension'] ?? null,
                position: isset($data['position']) ? (int) $data['position'] : null,
                isActive: $data['is_active'] ?? null,
            ),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (ProductAttribute $record): bool {
                    app(DeletesProductAttributes::class)->handle($record);

                    return true;
                }),
        ];
    }
}
