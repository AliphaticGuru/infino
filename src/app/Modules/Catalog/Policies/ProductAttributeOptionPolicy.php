<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Identity\Models\User;

final class ProductAttributeOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ProductAttributeOption $option): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, ProductAttributeOption $option): bool
    {
        return $user->is_admin;
    }

    /**
     * Referenced options are deactivated rather than deleted; the delete
     * action additionally rejects selected options.
     */
    public function delete(User $user, ProductAttributeOption $option): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
