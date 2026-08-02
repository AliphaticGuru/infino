<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Data\RegisterUserData;

interface RegistersUsers
{
    public function handle(RegisterUserData $data): User;
}