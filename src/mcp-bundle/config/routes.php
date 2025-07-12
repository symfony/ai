<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('_mcp_sse', '/sse')
        ->controller([McpController::class, 'sse'])
        ->methods(['GET'])
    ;
    $routes->add('_mcp_messages', '/messages/{id}')
        ->controller([McpController::class, 'messages'])
        ->methods(['POST'])
    ;
};
