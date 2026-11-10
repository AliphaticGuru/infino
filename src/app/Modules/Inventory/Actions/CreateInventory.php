<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Contracts\CreatesInventory;
use App\Modules\Inventory\Data\CreateInventoryData;
use App\Modules\Inventory\Models\Inventory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateInventory implements CreatesInventory
{
    public function handle(CreateInventoryData $data): Inventory
    {
        $validated = Validator::make(
            [
                'product_id' => $data->productId,
                'location' => $data->location,
                'quantity' => $data->quantity,
            ],
            [
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'location' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('inventories', 'location')
                        ->where(fn ($query) => $query->where('product_id', $data->productId)),
                ],
                'quantity' => ['required', 'integer', 'min:0'],
            ],
        )->validate();

        return Inventory::create([
            'product_id' => $validated['product_id'],
            'location' => $validated['location'],
            'quantity' => $validated['quantity'],
        ]);
    }
}
