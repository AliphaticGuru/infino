<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

final class VariantKey
{
    /**
     * Build the deterministic per-product variant signature.
     *
     * Pairs are sorted by the attribute position and then by the attribute
     * code, so the same selection set always produces the same key regardless
     * of input order.
     *
     * @param  array<int, array{attributeCode: string, optionCode: string, attributePosition: int}>  $pairs
     */
    public static function fromPairs(array $pairs): string
    {
        usort($pairs, static function (array $left, array $right): int {
            return [$left['attributePosition'], $left['attributeCode']]
                <=> [$right['attributePosition'], $right['attributeCode']];
        });

        return implode('|', array_map(
            static fn (array $pair): string => $pair['attributeCode'].':'.$pair['optionCode'],
            $pairs,
        ));
    }
}
