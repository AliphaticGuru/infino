<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Contracts\AddsProductMedia;
use App\Modules\Catalog\Contracts\DeletesProductMedia;
use App\Modules\Catalog\Contracts\FindsProductMedia;
use App\Modules\Catalog\Contracts\RepositionsProductMedia;
use App\Modules\Catalog\Contracts\SetsPrimaryProductMedia;
use App\Modules\Catalog\Contracts\UpdatesProductMedia;
use App\Modules\Catalog\Data\AddProductMediaData;
use App\Modules\Catalog\Data\RepositionProductMediaData;
use App\Modules\Catalog\Data\UpdateProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'allMedia';

    protected static ?string $title = 'Media';

    protected static ?string $recordTitleAttribute = 'original_name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Product) {
            return false;
        }

        if (! Gate::allows('manageMedia', $ownerRecord)) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Product);

        $tabs = [
            'product' => Tab::make('Product')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('product_variant_id')),
        ];

        foreach ($owner->variants()->orderBy('position')->orderBy('id')->get() as $variant) {
            $tabs['variant_'.$variant->getKey()] = Tab::make($this->variantLabel($variant))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('product_variant_id', $variant->getKey()));
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Product);

        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->disk(fn (ProductMedia $record): string => $record->disk)
                    ->imageSize(48)
                    ->square(),
                TextColumn::make('alt_text')
                    ->label('Alt text')
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('productVariant.label')
                    ->label('Gallery')
                    ->placeholder('Product')
                    ->toggleable(),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
                TextColumn::make('position')
                    ->sortable(),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1024, 1).' KB'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('uploadImage')
                    ->label('Upload image')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->authorize(fn (): bool => Gate::allows('manageMedia', $owner))
                    ->schema([
                        FileUpload::make('file')
                            ->label('Image')
                            ->image()
                            ->acceptedFileTypes((array) config('catalog.media.allowed_mime_types', []))
                            ->maxSize((int) config('catalog.media.max_size_kb', 5120))
                            ->storeFiles(false)
                            ->imageEditor(false)
                            ->required(),
                        Select::make('product_variant_id')
                            ->label('Gallery')
                            ->placeholder('Product gallery')
                            ->options(fn (): array => $owner->variants()
                                ->orderBy('position')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (ProductVariant $variant): array => [
                                    $variant->getKey() => $this->variantLabel($variant),
                                ])
                                ->all())
                            ->nullable(),
                        TextInput::make('alt_text')
                            ->label('Alt text')
                            ->maxLength(255),
                        Toggle::make('is_primary')
                            ->label('Primary image'),
                    ])
                    ->action(function (array $data, Action $action) use ($owner): void {
                        $file = $this->uploadedFile($data['file'] ?? null);

                        if ($file === null) {
                            throw ValidationException::withMessages([
                                'file' => 'An image must be uploaded.',
                            ]);
                        }

                        app(AddsProductMedia::class)->handle(
                            $owner,
                            new AddProductMediaData(
                                file: $file,
                                productVariantId: filled($data['product_variant_id'] ?? null)
                                    ? (int) $data['product_variant_id']
                                    : null,
                                altText: $data['alt_text'] ?? null,
                                isPrimary: (bool) ($data['is_primary'] ?? false),
                            ),
                        );

                        $action->success();
                    })
                    ->successNotificationTitle('Image uploaded'),
            ])
            ->recordActions([
                Action::make('editAltText')
                    ->label('Edit alt text')
                    ->icon(Heroicon::OutlinedPencil)
                    ->authorize('update')
                    ->schema([
                        TextInput::make('alt_text')
                            ->label('Alt text')
                            ->maxLength(255),
                    ])
                    ->fillForm(fn (ProductMedia $record): array => [
                        'alt_text' => $record->alt_text,
                    ])
                    ->action(function (ProductMedia $record, array $data, Action $action): void {
                        app(UpdatesProductMedia::class)->handle(
                            $record,
                            new UpdateProductMediaData(altText: $data['alt_text'] ?? null),
                        );

                        $action->success();
                    })
                    ->successNotificationTitle('Alt text updated'),
                Action::make('setPrimary')
                    ->label('Set primary')
                    ->icon(Heroicon::OutlinedStar)
                    ->authorize('setPrimary')
                    ->visible(fn (ProductMedia $record): bool => ! $record->is_primary)
                    ->action(function (ProductMedia $record, Action $action): void {
                        app(SetsPrimaryProductMedia::class)->handle($record);

                        $action->success();
                    })
                    ->successNotificationTitle('Primary image updated'),
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon(Heroicon::OutlinedArrowUp)
                    ->authorize('reorder')
                    ->visible(fn (ProductMedia $record): bool => $this->canMove($record, -1))
                    ->action(function (ProductMedia $record) use ($owner): void {
                        $this->move($owner, $record, -1);
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon(Heroicon::OutlinedArrowDown)
                    ->authorize('reorder')
                    ->visible(fn (ProductMedia $record): bool => $this->canMove($record, 1))
                    ->action(function (ProductMedia $record) use ($owner): void {
                        $this->move($owner, $record, 1);
                    }),
                DeleteAction::make()
                    ->authorize('delete')
                    ->using(function (ProductMedia $record): bool {
                        app(DeletesProductMedia::class)->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('position');
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->label
            ?: $variant->sku
            ?: 'Variant '.$variant->getKey();
    }

    private function uploadedFile(mixed $file): ?UploadedFile
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * @return list<int>
     */
    private function galleryIds(Product $owner, ProductMedia $record): array
    {
        $media = $record->product_variant_id === null
            ? app(FindsProductMedia::class)->forProduct($owner)
            : app(FindsProductMedia::class)->forVariant($record->productVariant);

        $ids = [];

        foreach ($media->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function canMove(ProductMedia $record, int $offset): bool
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Product);

        $ids = $this->galleryIds($owner, $record);

        $index = array_search($record->getKey(), $ids, true);

        if ($index === false) {
            return false;
        }

        return isset($ids[$index + $offset]);
    }

    private function move(Product $owner, ProductMedia $record, int $offset): void
    {
        $ids = $this->galleryIds($owner, $record);

        $index = array_search($record->getKey(), $ids, true);

        if ($index === false) {
            return;
        }

        $target = $index + $offset;

        if (! isset($ids[$target])) {
            return;
        }

        /** @var list<int> $ids */
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        app(RepositionsProductMedia::class)->handle(
            $owner,
            new RepositionProductMediaData(
                productVariantId: $record->product_variant_id,
                mediaIds: $ids,
            ),
        );
    }
}
