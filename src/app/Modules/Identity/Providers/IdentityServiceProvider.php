<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Identity\Actions\RegisterUser;
use App\Modules\Identity\Contracts\RegistersUsers;

final class IdentityServiceProvider extends BaseModuleServiceProvider
{   
    protected function registerModule(): void
    {
        $this->app->bind(
            RegistersUsers::class,
            RegisterUser::class,
            );
    }
}