<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Identity\Models\User;

final class ProductAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ProductAttribute $attribute): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, ProductAttribute $attribute): bool
    {
        return $user->is_admin;
    }

    /**
     * Referenced definitions are deactivated rather than deleted; the delete
     * action additionally rejects referenced definitions.
     */
    public function delete(User $user, ProductAttribute $attribute): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
