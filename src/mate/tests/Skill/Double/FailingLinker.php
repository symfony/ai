<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Skill\Double;

use Symfony\AI\Mate\Skill\LinkerInterface;

/**
 * Stands in for a filesystem without symlink support, so the copy fallback is reachable in tests.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FailingLinker implements LinkerInterface
{
    public function link(string $target, string $linkPath): bool
    {
        return false;
    }
}
