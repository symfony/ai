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

$modelCatalog = require __DIR__.'/model_catalog.php';

// Options that belong to one inference engine only, so that pairing them with another one fails
// instead of being silently dropped. Only keys without a default can be told apart from "unset".
$mantleOnly = ['api_key', 'region', 'credential_provider', 'http_client', 'path'];
$messagesOnly = ['cache_retention', 'workspace'];

/**
 * @param array<string, mixed> $config
 * @param list<string>         $keys
 */
$hasAny = static function (array $config, array $keys): bool {
    foreach ($keys as $key) {
        if (isset($config[$key])) {
            return true;
        }
    }

    return false;
};

return (new ArrayNodeDefinition('bedrock'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->enumNode('api')
                ->values(['invoke_model', 'completions', 'responses', 'messages'])
                ->defaultValue('invoke_model')
                ->info('Inference engine and protocol: the SDK-based InvokeModel API, or one of the Bedrock Mantle routes')
            ->end()
            ->stringNode('bedrock_runtime_client')
                ->defaultNull()
                ->info('Service ID of the Bedrock runtime client to use; only valid with "api: invoke_model"')
            ->end()
            ->stringNode('api_key')
                ->info('Bedrock API key; when omitted, requests are signed with AWS SigV4. Mantle only')
            ->end()
            ->stringNode('region')
                ->info('AWS region the Mantle base URL is derived from, defaults to "us-west-2". Mantle only')
            ->end()
            ->stringNode('credential_provider')
                ->info('Service ID of the AsyncAws credential provider used for SigV4 signing. Mantle only')
            ->end()
            ->stringNode('http_client')
                ->info('Service ID of the HTTP client to use, defaults to "http_client". Mantle only')
            ->end()
            ->stringNode('path')
                ->info('Overrides the Mantle request path, for models served on the other path prefix')
            ->end()
            ->enumNode('cache_retention')
                ->values(['none', 'short', 'long'])
                ->info('Anthropic Messages prompt-cache retention, defaults to "short"; only valid with "api: messages"')
            ->end()
            ->stringNode('workspace')
                ->info('Bedrock Mantle workspace ID sent with Anthropic Messages requests; only valid with "api: messages"')
            ->end()
            ->append($modelCatalog('static'))
        ->end()
        ->validate()
            ->ifTrue(static function (array $v) use ($hasAny, $mantleOnly, $messagesOnly): bool {
                if ('invoke_model' === $v['api']) {
                    return $hasAny($v, [...$mantleOnly, ...$messagesOnly]);
                }

                return null !== $v['bedrock_runtime_client'];
            })
            ->thenInvalid('The Bedrock Mantle options and "bedrock_runtime_client" belong to different inference engines and cannot be combined; check the "api" option.')
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => \in_array($v['api'], ['completions', 'responses'], true) && $hasAny($v, $messagesOnly))
            ->thenInvalid('The "cache_retention" and "workspace" options are only supported with "api: messages".')
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => 'messages' === $v['api'] && isset($v['path']))
            ->thenInvalid('The "path" option is not supported with "api: messages", which is served on a single path.')
        ->end()
    ->end();
