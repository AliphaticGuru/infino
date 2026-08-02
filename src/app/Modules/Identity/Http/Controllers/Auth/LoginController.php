<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Contracts\AuthenticatesUsers;
use App\Modules\Identity\Data\LoginData;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticatesUsers $authenticate,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = new LoginData(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            remember: $request->boolean('remember'),
        );

        $this->authenticate->handle($data);

        return redirect()->intended('/dashboard');
    }
}