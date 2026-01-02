MCP Bundle
==========

Symfony integration bundle for `Model Context Protocol`_ using the official MCP SDK `mcp/sdk`_.

Supports MCP capabilities (tools, prompts, resources) as server via HTTP transport and STDIO. Resource templates implementation ready but awaiting MCP SDK support.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/mcp-bundle

Usage
-----

At first, you need to decide whether your application should act as a MCP server or client. Both can be configured in
the ``mcp`` section of your ``config/packages/mcp.yaml`` file.
You also need to add few lines in the routing configuration for this bundle:

.. code-block:: yaml

    # config/routes.yaml
    mcp:
        resource: .
        type: mcp


Act as Server
~~~~~~~~~~~~~

To use your application as an MCP server, exposing tools, prompts, resources, and resource templates to clients like `Claude Desktop`_, you need to configure in the
``client_transports`` section the transports you want to expose to clients. You can use either STDIO or HTTP.

Creating MCP Capabilities
.........................

MCP capabilities are automatically discovered using PHP attributes.

Tools
^^^^^

Actions that can be executed::

    use Mcp\Capability\Attribute\McpTool;

    class CurrentTimeTool
    {
        #[McpTool(name: 'current-time')]
        public function getCurrentTime(string $format = 'Y-m-d H:i:s'): string
        {
            return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
        }
    }

Prompts
^^^^^^^

System instructions for AI context::

    use Mcp\Capability\Attribute\McpPrompt;

    class TimePrompts
    {
        #[McpPrompt(name: 'time-analysis')]
        public function getTimeAnalysisPrompt(): array
        {
            return [
                ['role' => 'user', 'content' => 'You are a time management expert.']
            ];
        }
    }

Resources
^^^^^^^^^

Static data that can be read::

    use Mcp\Capability\Attribute\McpResource;

    class TimeResource
    {
        #[McpResource(uri: 'time://current', name: 'current-time')]
        public function getCurrentTimeResource(): array
        {
            return [
                'uri' => 'time://current',
                'mimeType' => 'text/plain',
                'text' => (new \DateTime('now'))->format('Y-m-d H:i:s')
            ];
        }
    }

Resource Templates
^^^^^^^^^^^^^^^^^^

Dynamic resources with parameters:

.. note::

    Resource Templates are not yet functional as the underlying MCP SDK is missing the required handlers.
    See `MCP SDK issue #9 <https://github.com/modelcontextprotocol/php-sdk/issues/9>`_ for implementation status.

::

    use Mcp\Capability\Attribute\McpResourceTemplate;

    class TimeResourceTemplate
    {
        #[McpResourceTemplate(uriTemplate: 'time://{timezone}', name: 'time-by-timezone')]
        public function getTimeByTimezone(string $timezone): array
        {
            $time = (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s T');
            return [
                'uri' => "time://$timezone",
                'mimeType' => 'text/plain',
                'text' => $time
            ];
        }
    }

All capabilities are automatically discovered in the ``src/`` directory when the server starts.

Attribute Placement Patterns
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The MCP SDK, and therefore the MCP Bundle, supports two patterns for placing attributes on your capabilities:

**Invokable Pattern** - Attribute on a class with ``__invoke()`` method::

    #[McpTool(name: 'my-tool')]
    class MyTool
    {
        public function __invoke(string $param): string
        {
            // Implementation
        }
    }

**Method-Based Pattern** - Multiple attributes on individual methods::

    class MyTools
    {
        #[McpTool(name: 'tool-one')]
        public function toolOne(): string { }

        #[McpTool(name: 'tool-two')]
        public function toolTwo(): string { }
    }

Transport Types
...............

The MCP Bundle supports two transport types for server communication:

- **STDIO Transport** - For command-line clients (e.g., ``symfony console mcp:server``)
- **HTTP Transport** - For web-based clients and MCP Inspector using streamable HTTP connections

The HTTP transport uses the MCP SDK's ``StreamableHttpTransport`` which supports:

- JSON-RPC 2.0 over HTTP POST requests
- Session management with configurable storage (file/memory)
- CORS headers for cross-origin requests
- Proper MCP initialization handshake

Act as Client
~~~~~~~~~~~~~

.. warning::

    Not implemented yet, but planned for the future.

To use your application as an MCP client, integrating other MCP servers, you need to configure the ``servers`` you want
to connect to. You can use either STDIO or HTTP as transport methods.

You can find a list of example Servers in the `MCP Server List`_.

Tools of those servers are available in your `AI Bundle`_ configuration and usable in your agents.

Configuration
-------------

.. code-block:: yaml

    # config/packages/mcp.yaml
    mcp:
        app: 'app' # Application name to be exposed to clients
        version: '1.0.0' # Application version to be exposed to clients
        pagination_limit: 50 # Maximum number of items returned per list request (default: 50)
        instructions: | # Instructions describing server purpose and usage context (for LLMs)
            This server provides time management capabilities for developers.

            Use when working with timestamps, time zones, or time-based calculations.
            All timestamps are in UTC unless specified otherwise.

            Example contexts: logging, debugging, time-sensitive operations.

        client_transports:
            stdio: true # Enable STDIO via command
            http: true # Enable HTTP transport via controller

        # HTTP transport configuration (optional)
        http:
            path: /_mcp # HTTP endpoint path (default: /_mcp)
            session:
                store: file # Session store type: 'file' or 'memory' (default: file)
                directory: '%kernel.cache_dir%/mcp-sessions' # Directory for file store (default: cache_dir/mcp-sessions)
                ttl: 3600 # Session TTL in seconds (default: 3600)

        # OAuth 2.0 Discovery (RFC 9728)
        oauth:
            enabled: true
            authorization_servers:
                - 'https://auth.example.com'
            scopes_supported:
                - 'read'
                - 'write'

        # Not supported yet
        servers:
            name:
                transport: 'stdio' # Transport method to use, either 'stdio' or 'http'
                stdio:
                    command: 'php /path/bin/console mcp:server' # Command to execute to start the server
                    arguments: [] # Arguments to pass to the command
                http:
                    url: 'http://localhost:8000/_mcp' # URL to HTTP endpoint of MCP server

Logging Configuration
---------------------

By default, MCP uses a dedicated logger channel that inherits your application's default logging configuration.
To configure MCP-specific logging, add the following to your ``config/packages/monolog.yaml``:

.. code-block:: yaml

    # config/packages/monolog.yaml
    monolog:
        channels: ['mcp']
        handlers:
            mcp:
                type: rotating_file
                path: '%kernel.logs_dir%/mcp.log'
                level: info
                channels: ['mcp']
                max_files: 30

You can customize the logging level and destination according to your needs:

.. code-block:: yaml

    # Example: Different levels per environment
    monolog:
        handlers:
            mcp_dev:
                type: stream
                path: '%kernel.logs_dir%/mcp.log'
                level: debug
                channels: ['mcp']
            mcp_prod:
                type: slack
                level: error
                channels: ['mcp']
                webhook_url: '%env(SLACK_WEBHOOK)%'

Profiler
--------

When the Symfony Web Profiler is enabled, the MCP Bundle automatically adds a dedicated panel showing all registered MCP capabilities in your application:

.. image:: images/profiler-mcp.png
   :alt: MCP Profiler Panel

The profiler displays:

- **Tools**: All registered MCP tools with their descriptions and input schemas
- **Prompts**: Available prompts with their arguments and requirements
- **Resources**: Static resources with their URIs and MIME types
- **Resource Templates**: Dynamic resource templates with URI patterns

This makes it easy to inspect and debug your MCP server capabilities during development.

OAuth Discovery
---------------

The MCP Bundle implements OAuth 2.0 Protected Resource Metadata (`RFC 9728`_) to help MCP clients discover
how to authenticate with your server.

When OAuth is enabled, the bundle exposes:

- ``/.well-known/oauth-protected-resource`` - Metadata endpoint telling clients where to authenticate
- ``WWW-Authenticate`` header on 401/403 responses pointing to the discovery endpoint

Configuration
~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/mcp.yaml
    mcp:
        oauth:
            enabled: true
            authorization_servers:
                - 'https://auth.example.com'  # Your OAuth authorization server
            resource: 'https://mcp.example.com/_mcp'  # Optional: canonical URI of your MCP server
            scopes_supported:
                - 'read'
                - 'write'

How It Works
~~~~~~~~~~~~

1. Client calls your MCP endpoint without authentication
2. Your security layer returns ``401 Unauthorized``
3. The bundle adds ``WWW-Authenticate: Bearer resource_metadata=".../.well-known/oauth-protected-resource"``
4. Client fetches the metadata to discover the authorization server
5. Client authenticates with the authorization server and obtains a token
6. Client retries the MCP request with ``Authorization: Bearer <token>``

.. note::

    The bundle only provides OAuth **discovery**. To actually protect your MCP endpoint,
    you need to configure Symfony's security layer (see next section).

.. _`RFC 9728`: https://datatracker.ietf.org/doc/html/rfc9728

Protecting MCP Endpoints
------------------------

The MCP Bundle integrates with Symfony's security system to protect your MCP endpoints.
When ``symfony/security-bundle`` is installed, the bundle automatically enables scope checking.

Configuring Security
~~~~~~~~~~~~~~~~~~~~

Use Symfony's ``access_token`` authenticator to validate access tokens:

.. code-block:: yaml

    # config/packages/security.yaml
    security:
        firewalls:
            mcp:
                pattern: ^/_mcp
                stateless: true
                access_token:
                    token_handler: App\Security\McpTokenHandler

        access_control:
            - { path: ^/_mcp, roles: IS_AUTHENTICATED_FULLY }

Example token handler::

    namespace App\Security;

    use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

    class McpTokenHandler implements AccessTokenHandlerInterface
    {
        public function getUserBadgeFrom(string $accessToken): UserBadge
        {
            // Validate token with your OAuth provider
            $payload = $this->validateToken($accessToken);

            return new UserBadge(
                $payload['sub'],
                fn () => new McpUser($payload['sub'], $payload['scope'] ?? [])
            );
        }
    }

Per-Tool Scope Checking
~~~~~~~~~~~~~~~~~~~~~~~

Use the ``#[RequireScope]`` attribute to restrict access to specific tools based on OAuth scopes::

    use Mcp\Capability\Attribute\McpTool;
    use Symfony\AI\McpBundle\Security\Attribute\RequireScope;

    class AdminTools
    {
        #[McpTool(name: 'delete-user')]
        #[RequireScope('admin')]
        public function deleteUser(int $userId): string
        {
            // Only accessible with 'admin' scope
        }

        #[McpTool(name: 'list-users')]
        #[RequireScope(['read', 'write'])]  // Requires ALL scopes
        public function listUsers(): array
        {
            // Requires both scopes
        }
    }

Scope Extraction
~~~~~~~~~~~~~~~~

The bundle needs to know what OAuth scopes the current user has. Since Symfony doesn't have
a built-in concept of OAuth scopes, you need to provide this information through one of
two approaches:

**Approach 1: Using Symfony roles (default)**

The default ``RoleScopeExtractor`` reads roles with the ``ROLE_OAUTH2_`` prefix. This is
compatible with `league/oauth2-server-bundle`_ which uses the same convention.

If you use league/oauth2-server-bundle, scope checking works out of the box - no configuration needed.

For custom token handlers, convert OAuth scopes to Symfony roles::

    class MyTokenHandler implements AccessTokenHandlerInterface
    {
        public function getUserBadgeFrom(string $accessToken): UserBadge
        {
            $tokenData = $this->validateAndDecodeToken($accessToken);

            // Convert OAuth scopes to Symfony roles (ROLE_OAUTH2_ prefix)
            $roles = ['ROLE_USER'];
            foreach ($tokenData['scopes'] as $scope) {
                $roles[] = 'ROLE_OAUTH2_' . strtoupper($scope);
            }
            // ['read', 'admin'] → ['ROLE_USER', 'ROLE_OAUTH2_READ', 'ROLE_OAUTH2_ADMIN']

            return new UserBadge(
                $tokenData['user_id'],
                fn () => new InMemoryUser($tokenData['user_id'], null, $roles)
            );
        }
    }

.. _`league/oauth2-server-bundle`: https://github.com/thephpleague/oauth2-server-bundle

**Approach 2: Custom scope extractor**

If you prefer to keep OAuth scopes separate from Symfony roles, implement ``ScopeExtractorInterface``::

    use Symfony\AI\McpBundle\Security\ScopeExtractorInterface;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

    class DirectScopeExtractor implements ScopeExtractorInterface
    {
        public function extract(TokenInterface $token): array
        {
            // Read scopes directly from token attributes
            return $token->getAttribute('oauth_scopes') ?? [];
        }
    }

Store the scopes in your token handler::

    class MyTokenHandler implements AccessTokenHandlerInterface
    {
        public function getUserBadgeFrom(string $accessToken): UserBadge
        {
            $tokenData = $this->validateAndDecodeToken($accessToken);

            return new UserBadge(
                $tokenData['user_id'],
                fn () => new InMemoryUser($tokenData['user_id'], null, ['ROLE_USER']),
                // Store scopes in token attributes
                ['oauth_scopes' => $tokenData['scopes']]
            );
        }
    }

Then register your extractor:

.. code-block:: yaml

    # config/services.yaml
    services:
        Symfony\AI\McpBundle\Security\ScopeExtractorInterface:
            class: App\Security\DirectScopeExtractor

Event System
------------

The MCP Bundle automatically configures the Symfony EventDispatcher to work with the MCP SDK's event system.
This allows you to listen for changes to your server's capabilities.

Available Events
~~~~~~~~~~~~~~~~

The MCP SDK dispatches the following events when capabilities are registered:

- ``Mcp\Event\ToolListChangedEvent`` - When a tool is registered
- ``Mcp\Event\ResourceListChangedEvent`` - When a resource is registered
- ``Mcp\Event\ResourceTemplateListChangedEvent`` - When a resource template is registered
- ``Mcp\Event\PromptListChangedEvent`` - When a prompt is registered

Listening to Events
~~~~~~~~~~~~~~~~~~~

You can create event listeners to respond to capability changes::

    use Mcp\Event\ToolListChangedEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    class McpCapabilityListener
    {
        public function onToolListChanged(ToolListChangedEvent $event): void
        {
            // Handle tool registration
            // For example: invalidate cache, log changes, notify clients
        }
    }

The events are simple marker events that notify when lists have changed, but don't contain specific details about what was added or modified.

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
.. _`mcp/sdk`: https://github.com/modelcontextprotocol/php-sdk
.. _`Claude Desktop`: https://claude.ai/download
.. _`MCP Server List`: https://modelcontextprotocol.io/examples
.. _`AI Bundle`: https://github.com/symfony/ai-bundle
