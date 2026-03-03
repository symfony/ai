<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('vertexai'))
    ->validate()
        ->ifTrue(static function ($v) {
            $hasLocation = isset($v['location']);
            $hasProjectId = isset($v['project_id']);
            if ($hasLocation !== $hasProjectId) {
                return true;
            }

            return !$hasLocation && !isset($v['api_key']);
        })
        ->thenInvalid('VertexAI requires either both "location" and "project_id" for the project-scoped endpoint, or "api_key" alone for the global endpoint.')
    ->end()
    ->children()
        ->stringNode('location')->defaultNull()->info('Required for the project-scoped endpoint. Must be set together with "project_id".')->end()
        ->stringNode('project_id')->defaultNull()->info('Required for the project-scoped endpoint. Must be set together with "location".')->end()
        ->stringNode('api_key')->defaultNull()->info('When set without "location" and "project_id", uses the global endpoint. Note: API keys only identify the project for billing and do not provide identity-based access control. For production use with IAM, audit logging, or data residency, prefer the project-scoped endpoint with service account authentication.')->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
    ->end();
