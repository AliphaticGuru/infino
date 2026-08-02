<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Identity\Actions\RegisterUser;
use App\Modules\Identity\Contracts\RegistersUsers;
use App\Modules\Identity\Contracts\AuthenticatesUsers;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Contracts\LogsOutUsers;
use App\Modules\Identity\Actions\LogOutUser;

final class IdentityServiceProvider extends BaseModuleServiceProvider
{   
    protected function registerModule(): void
    {
        $this->app->bind(
            RegistersUsers::class,
            RegisterUser::class,
        );

        $this->app->bind(
            AuthenticatesUsers::class,
            AuthenticateUser::class,
        );

        $this->app->bind(
            LogsOutUsers::class,
            LogOutUser::class,
        );
    }
}