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

use Mcp\Schema\Enum\ProtocolVersion;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return static function (DefinitionConfigurator $configurator): void {
    /**
     * The kinds of element a server can expose, mapped to their label in the messages.
     */
    $kinds = [
        'tools' => 'tools',
        'prompts' => 'prompts',
        'resources' => 'resources',
        'resource_templates' => 'resource templates',
        'apps' => 'MCP Apps',
    ];

    /**
     * The elements of one kind a server exposes: service ids, FQCNs, namespace prefixes
     * (trailing backslash), or "*" for every element of that kind.
     */
    $elements = static fn (string $key, string $kind): ArrayNodeDefinition => (new ArrayNodeDefinition($key))
        ->info(\sprintf('The %s this server exposes: service ids, FQCNs, namespace prefixes (trailing "\\"), or "*" for all of them.', $kind))
        ->beforeNormalization()
            ->ifString()
            ->then(static fn (string $v): array => [$v])
        ->end()
        ->scalarPrototype()->cannotBeEmpty()->end()
        ->defaultValue([])
        ->validate()
            ->ifTrue(static fn (array $v): bool => \in_array('*', $v, true) && 1 < \count($v))
            ->thenInvalid('The "*" wildcard cannot be combined with explicit entries, got %s.')
        ->end()
    ;

    /**
     * What a server puts into its registry, in either of two forms: one pattern list for
     * every kind at once, or a map narrowing each kind separately.
     *
     *     registry: ['App\\Mcp\\']
     *     registry: { tools: ['App\\Mcp\\Tool\\'], apps: ['App\\Apps\\'] }
     *
     * @param array<string, string>                         $kinds
     * @param callable(string, string): ArrayNodeDefinition $elements
     */
    $registry = static function (array $kinds, callable $elements): ArrayNodeDefinition {
        $node = (new ArrayNodeDefinition('registry'))
            ->info('The elements this server exposes, either as one list covering every kind or as a map narrowing each kind.')
            ->isRequired()
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifTrue(static fn ($v): bool => \is_string($v) || (\is_array($v) && array_is_list($v)))
                ->then(static fn ($v): array => array_fill_keys(array_keys($kinds), \is_string($v) ? [$v] : $v))
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => [] === array_filter($v, static fn (array $patterns): bool => [] !== $patterns))
                ->thenInvalid('An MCP server must expose at least one of "tools", "prompts", "resources", "resource_templates" or "apps". Use "*" to expose everything.')
            ->end()
        ;

        foreach ($kinds as $key => $label) {
            $node->append($elements($key, $label));
        }

        return $node;
    };

    $namesAreValid = static fn (array $v): bool => [] === array_filter(
        array_keys($v),
        static fn (int|string $name): bool => !\is_string($name) || 1 !== preg_match('/^[a-zA-Z0-9_-]++$/', $name),
    );

    $configurator->rootNode()
        ->children()
            ->arrayNode('servers')
                ->info('The MCP servers this application exposes, each with its own identity, transports, session store and capabilities.')
                // No useAttributeAsKey() here: it would consume the "name" child as the array key.
                ->validate()
                    ->ifTrue(static fn (array $v): bool => !$namesAreValid($v))
                    ->thenInvalid('MCP server names must only contain letters, digits, underscores and hyphens, got %s.')
                ->end()
                ->arrayPrototype()
                    ->children()
                        ->stringNode('name')->defaultNull()->info('Name advertised to clients. Defaults to the configuration key.')->end()
                        ->stringNode('version')->defaultValue('0.0.1')->end()
                        ->stringNode('description')->defaultNull()->end()
                        ->arrayNode('icons')
                            ->arrayPrototype()
                                ->children()
                                    ->stringNode('src')->isRequired()->end()
                                    ->stringNode('mime_type')->defaultNull()->end()
                                    ->arrayNode('sizes')
                                        ->scalarPrototype()->end()
                                        ->defaultValue(['any'])
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->stringNode('website_url')->defaultNull()->end()
                        ->integerNode('pagination_limit')->min(1)->defaultValue(50)->end()
                        ->stringNode('instructions')->defaultNull()->end()

                        ->arrayNode('transports')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('stdio')->defaultFalse()->info('Expose the server over STDIO via the "mcp:server" command.')->end()
                                ->booleanNode('http')->defaultTrue()->info('Expose the server over HTTP via a controller and route.')->end()
                            ->end()
                        ->end()

                        ->arrayNode('http')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->stringNode('path')->defaultNull()->info('HTTP endpoint path. Defaults to "/mcp/<name>".')->end()
                                ->variableNode('allowed_hosts')
                                    ->info('DNS rebinding protection hosts (without port). Leave unset to keep the SDK default (localhost only), set an array of hostnames to expose a public MCP server, or false to disable the protection entirely.')
                                    ->defaultNull()
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => null !== $v && false !== $v && !\is_array($v))
                                        ->thenInvalid('The "allowed_hosts" option must be an array of hostnames or false, got %s.')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('protocol_versions')
                            ->info('Narrows the revisions this server answers for; left unset the SDK\'s own support stands. Each era takes what belongs to it: the modern revisions listed become the only ones that leg answers for, and listing none of them refuses the modern era. A handshake-era revision pins the handshake to exactly that one; listing none leaves it negotiating over every revision it knows, which the SDK offers no way to narrow further.')
                            ->beforeNormalization()->ifString()->then(static fn (string $v): array => [$v])->end()
                            ->enumPrototype()->values(array_column(ProtocolVersion::cases(), 'value'))->end()
                            ->defaultValue([])
                            ->validate()
                                // setProtocolVersion() pins the handshake to one revision; the SDK has no way to
                                // offer a subset of the others, so a list it cannot express is refused here.
                                ->ifTrue(static fn (array $v): bool => \count(array_filter($v, static fn (string $r): bool => !ProtocolVersion::from($r)->isModern())) > 1)
                                ->thenInvalid('The handshake era can be pinned to a single revision or left to negotiate, not narrowed to a subset, got %s.')
                            ->end()
                        ->end()

                        ->arrayNode('request_state')
                            ->addDefaultsIfNotSet()
                            ->info('Signs the state a multi-round-trip answer carries through the client, which has no session to keep progress in. Required for a modern-era server whose handlers return an InputRequiredResult, and for one whose handlers call ClientGateway::elicit() more than once: the second ask has to carry the first answer to the next round.')
                            ->children()
                                ->stringNode('key')->defaultNull()->info('HMAC key, at least 32 bytes. The same value must reach every process that might serve the retry.')->end()
                                ->integerNode('ttl')->min(1)->defaultValue(600)->info('Seconds a minted state stays valid.')->end()
                            ->end()
                        ->end()

                        ->arrayNode('cache')
                            ->addDefaultsIfNotSet()
                            ->info('Cache hints the modern-era leg puts on its answers. The spec requires them on server/discover, the list methods and resources/read.')
                            ->children()
                                ->integerNode('ttl_ms')->min(0)->defaultValue(0)->info('Default freshness in milliseconds. 0 refuses caching.')->end()
                                ->enumNode('scope')->values(['private', 'public'])->defaultValue('private')->end()
                                ->arrayNode('methods')
                                    ->info('Per-method overrides, e.g. "tools/list". Use "public" only for answers that do not vary by caller.')
                                    ->normalizeKeys(false)
                                    ->useAttributeAsKey('method')
                                    ->arrayPrototype()
                                        ->children()
                                            ->integerNode('ttl_ms')->min(0)->isRequired()->end()
                                            ->enumNode('scope')->values(['private', 'public'])->defaultValue('private')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('subscriptions')
                            ->addDefaultsIfNotSet()
                            ->info('Delivery for "subscriptions/listen" streams, which replace the HTTP GET stream in 2026-07-28.')
                            ->children()
                                ->enumNode('bus')->values(['none', 'memory', 'cache'])->defaultValue('none')->end()
                                ->stringNode('cache_pool')->defaultValue(McpBundle::DEFAULT_NOTIFICATION_CACHE_POOL)->info('PSR-16 service for the "cache" bus. Under PHP-FPM the publisher and the stream are different workers, so "memory" cannot reach them.')->end()
                                ->floatNode('lifetime')->min(0)->defaultValue(30.0)->info('Seconds a stream is held before the server closes it gracefully. 0 means until the client or the runtime ends it.')->end()
                            ->end()
                        ->end()

                        ->arrayNode('session')
                            ->addDefaultsIfNotSet()
                            ->info('Session storage. Every server needs its own store: session ids are not namespaced by server, so a shared store makes a session minted on one server valid on the others.')
                            ->children()
                                ->enumNode('store')->values(['file', 'memory', 'cache', 'framework'])->defaultValue('file')->end()
                                ->stringNode('directory')->defaultNull()->info('Directory for the "file" store. Defaults to "%kernel.cache_dir%/mcp-sessions/<name>".')->end()
                                ->stringNode('cache_pool')->defaultValue(McpBundle::DEFAULT_SESSION_CACHE_POOL)->info('PSR-16 cache service for the "cache" store.')->end()
                                ->stringNode('prefix')->defaultNull()->info('Key prefix for the "cache" and "framework" stores. Defaults to "mcp-<name>-".')->end()
                                ->integerNode('ttl')->min(1)->defaultValue(3600)->end()
                            ->end()
                        ->end()

                        ->append($registry($kinds, $elements))
                    ->end()
                ->end()
            ->end()

            ->arrayNode('clients')
                ->info('The MCP clients this application uses to reach remote MCP servers. Each client owns a named set of server connections.')
                ->defaultValue([])
                ->validate()
                    ->ifTrue(static fn (array $v): bool => !$namesAreValid($v))
                    ->thenInvalid('MCP client names must only contain letters, digits, underscores and hyphens, got %s.')
                ->end()
                ->arrayPrototype()
                    ->children()
                        ->arrayNode('client_info')
                            ->info('Identity advertised to every remote server of this client during the initialize handshake.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->stringNode('name')->defaultNull()->info('Defaults to the configuration key.')->end()
                                ->stringNode('version')->defaultValue('0.0.1')->end()
                                ->stringNode('description')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->enumNode('protocol_version')
                            ->info('MCP protocol version to negotiate. Leave unset to keep the SDK default.')
                            ->values(array_column(ProtocolVersion::cases(), 'value'))
                            ->defaultNull()
                        ->end()
                        ->arrayNode('capabilities')
                            ->info('Client capabilities advertised during the handshake. "roots", "sampling" and "elicitation" are derived from the handlers configured below.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('roots_list_changed')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->stringNode('roots')
                            ->info('Service id implementing Mcp\Client\Handler\Request\RootsCallbackInterface. Answers the server\'s "roots/list" requests.')
                            ->defaultNull()
                        ->end()
                        ->stringNode('sampling')
                            ->info('Service id implementing Mcp\Client\Handler\Request\SamplingCallbackInterface. Enables the "sampling" capability.')
                            ->defaultNull()
                        ->end()
                        ->stringNode('elicitation')
                            ->info('Service id implementing Mcp\Client\Handler\Request\ElicitationCallbackInterface. Enables the "elicitation" capability.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('forward_server_logs')
                            ->info('Forward logging notifications received from the remote servers to the "mcp" logger channel.')
                            ->defaultTrue()
                        ->end()

                        ->integerNode('init_timeout')->min(1)->defaultValue(30)->end()
                        ->integerNode('request_timeout')->min(1)->defaultValue(120)->end()
                        ->integerNode('max_retries')->min(0)->defaultValue(3)->end()

                        ->arrayNode('servers')
                            ->info('The remote MCP servers this client connects to.')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => !$namesAreValid($v))
                                ->thenInvalid('MCP server names must only contain letters, digits, underscores and hyphens, got %s.')
                            ->end()
                            ->arrayPrototype()
                                ->children()
                                    ->enumNode('transport')
                                        ->values(['stdio', 'http'])
                                        ->isRequired()
                                        ->info('How the server is reached: as a child process (stdio) or over a remote HTTP endpoint (http).')
                                    ->end()

                                    ->arrayNode('command')
                                        ->info('Program and arguments for the stdio transport, e.g. ["npx", "-y", "@modelcontextprotocol/server-filesystem", "/tmp"]. The first element is the program, not a shell string.')
                                        ->scalarPrototype()->end()
                                        ->defaultValue([])
                                    ->end()
                                    ->stringNode('cwd')->defaultNull()->info('Working directory of the stdio child process.')->end()
                                    ->arrayNode('env')
                                        ->info('Environment variables for the stdio child process.')
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                        ->defaultValue([])
                                    ->end()
                                    ->booleanNode('inherit_env')
                                        ->info('Merge "env" on top of the current process environment instead of replacing it.')
                                        ->defaultTrue()
                                    ->end()
                                    ->integerNode('max_buffer_size')->min(1)->defaultNull()->info('Maximum bytes buffered while waiting for a newline. Defaults to the SDK value.')->end()

                                    ->stringNode('url')->defaultNull()->info('Endpoint URL of the remote MCP server.')->end()
                                    ->arrayNode('headers')
                                        ->info('Additional request headers, e.g. Authorization.')
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                        ->defaultValue([])
                                    ->end()
                                    ->stringNode('http_client')
                                        ->info('Service id of a PSR-18 HTTP client. Defaults to "psr18.http_client" when available.')
                                        ->defaultNull()
                                    ->end()
                                    ->integerNode('max_sse_buffer_bytes')->min(1)->defaultNull()->info('Maximum bytes buffered per SSE event. Defaults to the SDK value.')->end()

                                    ->integerNode('init_timeout')->min(1)->defaultNull()->info('Overrides the client-level value.')->end()
                                    ->integerNode('request_timeout')->min(1)->defaultNull()->info('Overrides the client-level value.')->end()
                                    ->integerNode('max_retries')->min(0)->defaultNull()->info('Overrides the client-level value.')->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $v): bool => 'stdio' === $v['transport'] && [] === $v['command'])
                                    ->thenInvalid('A "command" must be configured for the "stdio" transport.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $v): bool => 'http' === $v['transport'] && null === $v['url'])
                                    ->thenInvalid('A "url" must be configured for the "http" transport.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $v): bool => 'stdio' === $v['transport']
                                        && (null !== $v['url'] || [] !== $v['headers'] || null !== $v['http_client'] || null !== $v['max_sse_buffer_bytes']))
                                    ->thenInvalid('The "url", "headers", "http_client" and "max_sse_buffer_bytes" options are only supported by the "http" transport.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $v): bool => 'http' === $v['transport']
                                        && ([] !== $v['command'] || null !== $v['cwd'] || [] !== $v['env'] || null !== $v['max_buffer_size']))
                                    ->thenInvalid('The "command", "cwd", "env" and "max_buffer_size" options are only supported by the "stdio" transport.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};
