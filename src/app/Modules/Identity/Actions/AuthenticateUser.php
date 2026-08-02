<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Contracts\AuthenticatesUsers;
use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AuthenticateUser implements AuthenticatesUsers
{
    public function handle(LoginData $data): User
    {
        if (! Auth::attempt([
            'email' => $data->email,
            'password' => $data->password,
        ], $data->remember)) {

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}