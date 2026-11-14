<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\VariantPrice\Pages;

use App\Modules\Pricing\Filament\Resources\VariantPrice\Schemas\VariantPriceForm;
use Filament\Resources\Pages\CreateRecord;

final class CreateVariantPrice extends CreateRecord
{
    protected static string $resource = \App\Modules\Pricing\Filament\Resources\VariantPriceResource::class;

    protected function getForm(): ?Schema
    {
        return VariantPriceForm::make();
    }
}
