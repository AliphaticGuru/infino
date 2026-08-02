<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Core\Data\Data;

final readonly class LoginData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {
    }
}