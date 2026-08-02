<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Models\User;

interface RegistersUsers
{
    public function handle(array $data): User;
}