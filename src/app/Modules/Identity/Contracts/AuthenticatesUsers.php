<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Models\User;

interface AuthenticatesUsers
{
    public function handle(LoginData $data): User;
}