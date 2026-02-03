<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Router;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Router\Result\RoutingResult;

/**
 * Composite router that tries multiple routers in sequence.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ChainRouter implements RouterInterface
{
    /**
     * @param iterable<RouterInterface> $routers
     */
    public function __construct(
        private readonly iterable $routers,
    ) {
    }

    public function route(Input $input, RouterContext $context): ?RoutingResult
    {
        foreach ($this->routers as $router) {
            $result = $router->route($input, $context);
            if (null !== $result) {
                return $result; // First match wins
            }
        }

        return null; // None matched
    }
}
