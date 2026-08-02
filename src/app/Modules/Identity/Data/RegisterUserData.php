<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Core\Data\Data;

final readonly class RegisterUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}