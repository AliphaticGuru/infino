<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers;

use App\Modules\Catalog\Contracts\CreatesProductAttributeOptions;
use App\Modules\Catalog\Contracts\DeletesProductAttributeOptions;
use App\Modules\Catalog\Contracts\RepositionsProductAttributeOptions;
use App\Modules\Catalog\Contracts\UpdatesProductAttributeOptions;
use App\Modules\Catalog\Data\CreateProductAttributeOptionData;
use App\Modules\Catalog\Data\UpdateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

final class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Options';

    protected static ?string $recordTitleAttribute = 'label';

    public function form(Schema $schema): Schema
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof ProductAttribute);

        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/')
                    ->unique(
                        table: 'product_attribute_options',
                        column: 'code',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('product_attribute_id', $owner->getKey()),
                    )
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->helperText('Immutable machine identifier, unique within this attribute.'),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                TextInput::make('position')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof ProductAttribute);

        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data) use ($owner): ProductAttributeOption {
                        return app(CreatesProductAttributeOptions::class)->handle(
                            $owner,
                            new CreateProductAttributeOptionData(
                                code: $data['code'],
                                label: $data['label'],
                                position: (int) ($data['position'] ?? 0),
                                isActive: $data['is_active'] ?? true,
                            ),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProductAttributeOption $record, array $data): ProductAttributeOption {
                        return app(UpdatesProductAttributeOptions::class)->handle(
                            $record,
                            new UpdateProductAttributeOptionData(
                                label: $data['label'] ?? null,
                                position: isset($data['position']) ? (int) $data['position'] : null,
                                isActive: $data['is_active'] ?? null,
                            ),
                        );
                    }),
                DeleteAction::make()
                    ->using(function (ProductAttributeOption $record): bool {
                        app(DeletesProductAttributeOptions::class)->handle($record);

                        return true;
                    }),
            ])
            ->reorderable('position')
            ->authorizeReorder(fn (): bool => Gate::allows('update', $owner))
            ->afterReordering(function (array $order) use ($owner): void {
                app(RepositionsProductAttributeOptions::class)->handle($owner, $order);
            })
            ->defaultSort('position');
    }
}
