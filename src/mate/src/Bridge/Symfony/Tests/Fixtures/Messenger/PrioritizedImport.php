<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger;

final class PrioritizedImport
{
    /**
     * @param list<Priority> $history
     */
    public function __construct(
        public readonly Priority $priority,
        public readonly array $history = [],
    ) {
    }
}
