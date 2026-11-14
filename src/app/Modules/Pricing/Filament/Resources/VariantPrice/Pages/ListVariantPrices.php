<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\VariantPrice\Pages;

use App\Modules\Pricing\Filament\Resources\VariantPriceResource;
use Filament\Resources\Pages\ListRecords;

final class ListVariantPrices extends ListRecords
{
    protected static string $resource = \App\Modules\Pricing\Filament\Resources\VariantPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
