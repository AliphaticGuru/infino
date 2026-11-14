<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Pages;

use App\Modules\Catalog\Filament\Resources\Attributes\AttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAttributes extends ListRecords
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
