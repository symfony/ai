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

return (new ArrayNodeDefinition('bedrockmantle'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('api_key')
                ->info('Bedrock API key sent as a bearer token; when omitted, requests are signed with AWS SigV4')
            ->end()
            ->stringNode('region')
                ->defaultValue('us-west-2')
                ->info('AWS region the base URL is derived from')
            ->end()
            ->enumNode('api')
                ->values(['completions', 'responses'])
                ->defaultValue('completions')
                ->info('OpenAI-compatible API to target: Chat Completions or Responses')
            ->end()
            ->stringNode('credential_provider')
                ->info('Service ID of the AsyncAws credential provider used for SigV4 signing')
            ->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
                ->info('Service ID of the HTTP client to use')
            ->end()
            ->stringNode('model_catalog')
                ->info('Service ID of the model catalog to use')
            ->end()
        ->end()
    ->end();
