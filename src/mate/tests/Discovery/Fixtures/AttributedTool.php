<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery\Fixtures;

use Symfony\AI\Mate\Attribute\MateTool;

/**
 * The class-level attributes are the point: `SampleColor::class` yields a T_CLASS token,
 * and the `Marker` following it must not be mistaken for the declared class name.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[Marker(SampleColor::class)]
#[Marker('second')]
final class AttributedTool
{
    #[MateTool(name: 'attributed-sample', title: 'Attributed Sample', description: 'Tool on a class carrying ::class attributes')]
    public function run(): string
    {
        return '';
    }
}
