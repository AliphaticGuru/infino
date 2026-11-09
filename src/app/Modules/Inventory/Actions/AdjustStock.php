<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AdjustStock implements AdjustsStock
{
    public function handle(Inventory $inventory, AdjustStockData $data): Inventory
    {
        $validated = Validator::make(
            [
                'quantity_delta' => $data->quantityDelta,
            ],
            [
                'quantity_delta' => ['required', 'integer', 'not_in:0'],
            ],
        )->validate();

        return DB::transaction(function () use ($inventory, $validated): Inventory {
            $lockedInventory = Inventory::query()
                ->whereKey($inventory->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $newQuantity = $lockedInventory->quantity + $validated['quantity_delta'];

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => 'The stock quantity cannot become negative.',
                ]);
            }

            $lockedInventory->quantity = $newQuantity;
            $lockedInventory->save();

            return $lockedInventory->refresh();
        });
    }
}
