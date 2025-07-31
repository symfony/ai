<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\McpBundle\Controller\McpSseController;
use Symfony\AI\McpBundle\Controller\McpHttpStreamController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('_mcp_sse', '/sse')
        ->controller([McpSseController::class, 'sse'])
        ->methods(['GET'])
    ;
    $routes->add('_mcp_messages', '/messages/{id}')
        ->controller([McpSseController::class, 'messages'])
        ->methods(['POST'])
    ;
    $routes->add('_mcp_http', '/http/')
        ->controller([McpHttpStreamController::class, 'endpoint'])
        ->methods(['POST'])
    ;
    $routes->add('_mcp_http_initiate_sse', '/http/')
        ->controller([McpHttpStreamController::class, 'initiateSseFromStream'])
        ->methods(['GET'])
    ;
    $routes->add('_mcp_http_delete_session', '/http/')
        ->controller([McpHttpStreamController::class, 'deleteSession'])
        ->methods(['DELETE'])
    ;
};
