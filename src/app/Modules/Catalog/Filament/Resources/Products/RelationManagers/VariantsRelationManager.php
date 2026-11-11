<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductVariantForm;
use App\Modules\Catalog\Filament\Resources\Products\Tables\ProductVariantsTable;
use App\Modules\Catalog\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Schema $schema): Schema
    {
        return ProductVariantForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Product);

        return ProductVariantsTable::configure($table, $owner);
    }
}
