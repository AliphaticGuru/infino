<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\VariantPrice\Pages;

use App\Modules\Pricing\Filament\Resources\VariantPrice\Schemas\VariantPriceForm;
use Filament\Resources\Pages\EditRecord;

final class EditVariantPrice extends EditRecord
{
    protected static string $resource = \App\Modules\Pricing\Filament\Resources\VariantPriceResource::class;

    protected function getForm(): ?Schema
    {
        return VariantPriceForm::make();
    }
}
