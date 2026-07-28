<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Exception;

/**
 * Thrown when two packages ship a skill that resolves to the same installed name.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillCollisionException extends InvalidArgumentException
{
}
