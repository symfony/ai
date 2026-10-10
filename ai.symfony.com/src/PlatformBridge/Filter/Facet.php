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

use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Option;
use App\PlatformBridge\Taxonomy\Region;

/**
 * A dimension the platform bridges can be filtered by. The value is the name of the
 * query parameter holding the selected options, the order of the cases is the order
 * of the filters on the page.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Facet: string
{
    case Capability = 'capability';
    case Kind = 'kind';
    case Deployment = 'runs';
    case Hosting = 'hosting';
    case Region = 'region';
    case ModelAccess = 'access';

    public function label(): string
    {
        return match ($this) {
            self::Capability => 'Capabilities',
            self::Deployment => 'Runs',
            self::Hosting => 'Hosting',
            self::Region => 'Server location',
            self::ModelAccess => 'Models',
            self::Kind => 'Type',
        };
    }

    /**
     * @return list<Option>
     */
    public function options(): array
    {
        return match ($this) {
            self::Capability => Capability::cases(),
            self::Deployment => Deployment::cases(),
            self::Hosting => Hosting::cases(),
            self::Region => Region::selectable(),
            self::ModelAccess => ModelAccess::cases(),
            self::Kind => Kind::cases(),
        };
    }

    public function option(string $value): ?Option
    {
        foreach ($this->options() as $option) {
            if ($value === $option->value) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Whether a bridge must have all the selected options rather than any of them: a
     * bridge running both in the cloud and locally is found by selecting both.
     */
    public function requiresAll(): bool
    {
        return \in_array($this, [self::Capability, self::Deployment, self::Hosting], true);
    }
}
