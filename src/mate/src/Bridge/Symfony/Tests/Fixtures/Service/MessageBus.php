<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service;

/**
 * Mirrors the constructor of Symfony\\Component\\Messenger\\MessageBus, which the bridge does
 * not depend on. The parameter name is what matters: it is the name the redaction rules see.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MessageBus
{
    /**
     * @param iterable<object> $middlewareHandlers
     */
    public function __construct(
        public readonly iterable $middlewareHandlers = [],
    ) {
    }
}
