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
 * Thrown when a skill name passed to a skills:* command is owned by more than one package.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AmbiguousSkillException extends InvalidArgumentException
{
}
