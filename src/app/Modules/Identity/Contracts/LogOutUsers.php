<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

interface LogsOutUsers
{
    public function handle(): void;
}