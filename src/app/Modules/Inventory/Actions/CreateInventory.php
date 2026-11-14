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
                'variant_id' => $data->variantId,
                'location' => $data->location,
                'quantity' => $data->quantity,
            ],
            [
                'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
                'location' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('inventories', 'location')
                        ->where(fn ($query) => $query->where('variant_id', $data->variantId)),
                ],
                'quantity' => ['required', 'integer', 'min:0'],
            ],
        )->validate();

        return Inventory::create([
            'variant_id' => $validated['variant_id'],
            'location' => $validated['location'],
            'quantity' => $validated['quantity'],
        ]);
    }
}
