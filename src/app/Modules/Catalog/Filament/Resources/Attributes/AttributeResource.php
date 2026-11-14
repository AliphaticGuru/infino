<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes;

use App\Modules\Catalog\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Modules\Catalog\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Modules\Catalog\Filament\Resources\Attributes\Tables\AttributesTable;
use App\Modules\Catalog\Models\ProductAttribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AttributeResource extends Resource
{
    protected static ?string $model = ProductAttribute::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return AttributeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            OptionsRelationManager::class,
        ];
    }
}
