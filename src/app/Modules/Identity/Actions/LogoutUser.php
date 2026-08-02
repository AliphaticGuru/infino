<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Contracts\LogsOutUsers;
use Illuminate\Support\Facades\Auth;

final class LogoutUser implements LogsOutUsers
{
    public function handle(): void
    {
        Auth::logout();
    }
}