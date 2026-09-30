<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Filter;

use App\PlatformBridge\PlatformBridge;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Sort: string
{
    case Popularity = 'popularity';
    case Name = 'name';
    case Capabilities = 'capabilities';

    public function label(): string
    {
        return match ($this) {
            self::Popularity => 'Popularity',
            self::Name => 'Name',
            self::Capabilities => 'Most capabilities',
        };
    }

    /**
     * @param list<PlatformBridge> $bridges sorted by name
     *
     * @return list<PlatformBridge>
     */
    public function apply(array $bridges): array
    {
        // usort is stable, ties stay sorted by name
        match ($this) {
            self::Popularity => usort($bridges, static fn (PlatformBridge $a, PlatformBridge $b): int => ($b->downloads ?? -1) <=> ($a->downloads ?? -1)),
            self::Capabilities => usort($bridges, static fn (PlatformBridge $a, PlatformBridge $b): int => \count($b->capabilities) <=> \count($a->capabilities)),
            self::Name => null,
        };

        return $bridges;
    }
}
