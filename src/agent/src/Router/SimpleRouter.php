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
 * Simple callable-based router for flexible routing logic.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SimpleRouter implements RouterInterface
{
    /**
     * @param callable(Input, RouterContext): ?RoutingResult $routingFunction
     */
    public function __construct(
        private readonly mixed $routingFunction,
    ) {
    }

    public function route(Input $input, RouterContext $context): ?RoutingResult
    {
        return ($this->routingFunction)($input, $context);
    }
}
