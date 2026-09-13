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

/**
 * Int-backed on purpose: CLI values always arrive as strings, so the caster has to coerce.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
enum SampleLevel: int
{
    case Low = 1;
    case High = 2;
}
