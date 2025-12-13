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
 * Routes requests to appropriate AI models based on input characteristics.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface RouterInterface
{
    /**
     * Routes request to appropriate model, optionally with transformation.
     *
     * @param Input         $input   The input containing messages and current model
     * @param RouterContext $context Context for routing (platform, catalog)
     *
     * @return RoutingResult|null Returns null if router cannot handle
     */
    public function route(Input $input, RouterContext $context): ?RoutingResult;
}
