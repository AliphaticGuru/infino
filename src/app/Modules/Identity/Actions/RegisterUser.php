<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Contracts\RegistersUsers;
use App\Modules\Identity\Data\RegisterUserData;

final class RegisterUser implements RegistersUsers
{
    public function handle(RegisterUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);
    }
}