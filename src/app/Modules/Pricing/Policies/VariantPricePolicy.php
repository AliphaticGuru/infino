<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\VariantPrice;

final class VariantPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, VariantPrice $price): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, VariantPrice $price): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, VariantPrice $price): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
