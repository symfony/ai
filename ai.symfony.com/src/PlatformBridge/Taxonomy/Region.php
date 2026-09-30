<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Taxonomy;

/**
 * Where requests are processed, i.e. the data residency options offered for the
 * API the bridge talks to.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Region: string implements Option
{
    case Worldwide = 'worldwide';
    case UnitedStates = 'us';
    case Europe = 'europe';
    case Asia = 'asia';
    /**
     * Self-hosted software runs wherever you deploy it, so it satisfies any region.
     */
    case Anywhere = 'anywhere';

    /**
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Worldwide, self::UnitedStates, self::Europe, self::Asia];
    }

    public function label(): string
    {
        return match ($this) {
            self::Worldwide => 'Worldwide',
            self::UnitedStates => 'United States',
            self::Europe => 'Europe',
            self::Asia => 'Asia-Pacific',
            self::Anywhere => 'Your infrastructure',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Worldwide => 'Regions on several continents to choose from, like hyperscalers offer.',
            self::UnitedStates => 'Requests can be processed in the United States.',
            self::Europe => 'Requests can be processed in Europe.',
            self::Asia => 'Requests can be processed in Asia-Pacific, e.g. in Japan, Singapore, India or Australia.',
            self::Anywhere => 'Runs wherever you deploy it, so it matches every region.',
        };
    }
}
