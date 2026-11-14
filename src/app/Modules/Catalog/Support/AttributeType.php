<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

enum AttributeType: string
{
    case Select = 'select';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
