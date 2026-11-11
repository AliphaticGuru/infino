<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;

final class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->is_admin;
    }

    /**
     * Variants are deactivated rather than deleted once inventory or movement
     * history can reference them.
     */
    public function delete(User $user, ProductVariant $variant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function activate(User $user, ProductVariant $variant): bool
    {
        return $user->is_admin;
    }

    public function deactivate(User $user, ProductVariant $variant): bool
    {
        return $user->is_admin;
    }

    public function reorder(User $user): bool
    {
        return $user->is_admin;
    }
}
