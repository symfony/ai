Creating MCP Extensions
=======================

MCP extensions are Composer packages that declare themselves using a specific configuration
in ``composer.json``, similar to PHPStan extensions.

Quick Start
-----------

You can also start from the official extension template:
`matesofmate/extension-template <https://github.com/matesofmate/extension-template>`_.

1. Configure composer.json
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: json

    {
        "name": "vendor/my-extension",
        "type": "library",
        "require": {
            "symfony/ai-mate": "^0.8"
        },
        "extra": {
            "ai-mate": {
                "scan-dirs": ["src", "lib"],
                "instructions": "INSTRUCTIONS.md"
            }
        }
    }

The ``extra.ai-mate`` section is required for your package to be discovered as an extension.
If your package uses Mate internally but must not be exposed as a reusable extension, set
``"extension": false`` in ``extra.ai-mate``.

2. Create MCP Capabilities
~~~~~~~~~~~~~~~~~~~~~~~~~~

::

    use Mcp\Capability\Attribute\McpTool;
    use Psr\Log\LoggerInterface;

    class MyTool
    {
        // Dependencies are automatically injected
        public function __construct(
            private LoggerInterface $logger,
        ) {
        }

        #[McpTool(name: 'my-tool', description: 'What this tool does')]
        public function execute(string $param): string
        {
            $this->logger->info('Tool executed', ['param' => $param]);

            return 'Result: ' . $param;
        }
    }

3. Install and Enable
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ composer require vendor/my-extension
    $ vendor/bin/mate discover

The ``discover`` command will automatically add your extension to ``mate/extensions.php``::

    return [
        'vendor/my-extension' => ['enabled' => true],
    ];

When the host project is already initialized, Composer install/update will also refresh discovery
automatically through the Mate Composer plugin.

To disable an extension, set ``enabled`` to ``false``::

    return [
        'vendor/my-extension' => ['enabled' => true],
        'vendor/unwanted-extension' => ['enabled' => false],
    ];

Dependency Injection
--------------------

Tools, Resources, and Prompts support constructor dependency injection via Symfony's DI Container.
Dependencies are automatically resolved and injected.

Configuring Services
~~~~~~~~~~~~~~~~~~~~

Register service configuration files in your ``composer.json``:

.. code-block:: json

    {
        "extra": {
            "ai-mate": {
                "scan-dirs": ["src"],
                "includes": [
                    "config/services.php"
                ]
            }
        }
    }

Create service configuration files using Symfony DI format::

    // config/services.php
    use App\MyApiClient;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    return function (ContainerConfigurator $configurator) {
        $services = $configurator->services();

        // Register a service with parameters
        $services->set(MyApiClient::class)
            ->arg('$apiKey', '%env(MY_API_KEY)%')
            ->arg('$baseUrl', 'https://api.example.com');
    };

Tool Output Schemas
-------------------

By default, tool results are encoded with TOON (or JSON when ``helgesverre/toon``
is not installed) and shipped only in MCP's text content block. The LLM reads the
encoded payload and the encoding's compactness keeps token usage low.

Tools that opt in via the ``#[StructuredOutput]`` attribute additionally:

- expose a JSON Schema generated from the return type docblock on the tool's
  ``outputSchema`` field (advertised in the ``tools/list`` response),
- populate MCP's ``structuredContent`` channel with the raw return value, so
  clients that validate or consume structured tool output can do so directly.

The schema is also surfaced in the ``mcp:tools:inspect`` command and in the
JSON / TOON output of ``mcp:tools:list`` and ``mcp:tools:inspect``.

When to opt in
~~~~~~~~~~~~~~

Modern MCP clients prefer ``structuredContent`` when present and feed it to the
LLM as JSON, bypassing the text content block. That makes ``#[StructuredOutput]``
a trade-off: machine-readable structure and schema validation, in exchange for
losing the TOON encoding's token savings on every call.

Use ``#[StructuredOutput]`` for small, fixed-shape, infrequently called tools
where structure helps the LLM more than TOON savings would — discovery tools,
single-resource lookups, configuration introspection. Skip it for bulk or
hot-path tools that return large or repeated payloads (search, list, dump);
those keep the TOON-only behavior and stay cheap.

The built-in tools follow this rule: ``server-info``, ``monolog-list-files``,
``monolog-list-channels``, and ``symfony-profiler-get`` opt in;
``monolog-search``, ``monolog-context-search``, ``monolog-tail``,
``symfony-services``, and ``symfony-profiler-list`` do not.

Basic Example
~~~~~~~~~~~~~

::

    use Mcp\Capability\Attribute\McpTool;
    use Symfony\AI\Mate\Mcp\Attribute\StructuredOutput;

    final class StatusTool
    {
        /**
         * @return array{status: string, uptime: int, message?: string}
         */
        #[McpTool('server-status', 'Get the current server status')]
        #[StructuredOutput]
        public function getStatus(): array
        {
            return ['status' => 'ok', 'uptime' => 12345];
        }
    }

The schema generator reads ``@phpstan-return`` (preferred) or ``@return`` and
converts the PHPStan array shape into a JSON Schema with two required properties
(``status``, ``uptime``) and one optional property (``message``).

Methods without ``#[StructuredOutput]`` may return any type; their result is
encoded into the text content block via ``ResponseEncoder`` regardless.

Supported PHPStan Syntax
~~~~~~~~~~~~~~~~~~~~~~~~

The generator understands common PHPStan type expressions:

- Array shapes: ``array{name: string, age: int}``
- Optional keys: ``array{status: string, message?: string}``
- Nullable values: ``array{parent: string|null}``
- Nested shapes: ``array{entries: list<array{id: int, title: string}>}``
- Lists: ``list<string>``, ``list<array{...}>``
- Maps: ``array<string, mixed>``, ``array<string, class-string|null>``
- Typed arrays: ``string[]``, ``int[]``

The top-level type must resolve to an object schema (an ``array{...}`` shape or
``array<string, T>`` map). MCP requires ``structuredContent`` to be a JSON
object, so a top-level list or scalar return type produces no schema even if
``#[StructuredOutput]`` is present.

Methods without a docblock return type, with ``mixed``, or with ``void`` return
types do not produce an output schema.

Sharing Types Across Classes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Use ``@phpstan-type`` to define a reusable shape and ``@phpstan-import-type`` to
reference it from another class::

    /**
     * @phpstan-type ProfileData array{
     *     token: string,
     *     method: string,
     *     url: string,
     *     status_code: int|null
     * }
     */
    final class Profile
    {
        // ...
    }

    use Symfony\AI\Mate\Mcp\Attribute\StructuredOutput;

    /**
     * @phpstan-import-type ProfileData from Profile
     */
    final class ProfileLookupTool
    {
        /**
         * @phpstan-return ProfileData
         */
        #[McpTool('profile-get', 'Get a profile by token')]
        #[StructuredOutput]
        public function get(string $token): array
        {
            // ...
        }
    }

The imported type is resolved against the declaring class at discovery time.

Configuration Reference
-----------------------

Scan Directories
~~~~~~~~~~~~~~~~

``extra.ai-mate.scan-dirs`` (optional)

- Default: Package root directory
- Relative to package root
- Multiple directories supported

Service Includes
~~~~~~~~~~~~~~~~

``extra.ai-mate.includes`` (optional)

- Array of service configuration file paths
- Standard Symfony DI configuration format (PHP files)
- Supports environment variables via ``%env()%``

Agent Instructions
~~~~~~~~~~~~~~~~~~

``extra.ai-mate.instructions`` (optional)

- Path to a markdown file containing instructions for AI agents
- Relative to package root
- Conventionally named ``INSTRUCTIONS.md``
- Content is aggregated and provided to AI assistants during MCP handshake

Example configuration:

.. code-block:: json

    {
        "extra": {
            "ai-mate": {
                "scan-dirs": ["src"],
                "instructions": "INSTRUCTIONS.md"
            }
        }
    }

Extension Discovery Opt-Out
~~~~~~~~~~~~~~~~~~~~~~~~~~~

``extra.ai-mate.extension`` (optional)

- Default: ``true``
- Set to ``false`` to exclude the package from Mate extension discovery
- Useful for applications or internal tooling packages that use Mate but should not appear as installable extensions

Example opt-out:

.. code-block:: json

    {
        "extra": {
            "ai-mate": {
                "extension": false,
                "scan-dirs": ["mate/src"]
            }
        }
    }

Writing Effective Agent Instructions
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Agent instructions help AI assistants understand when and how to use your extension's tools.
A good ``INSTRUCTIONS.md`` file should:

1. **Map CLI commands to MCP tools** - Show what tools replace common CLI operations
2. **Highlight benefits** - Explain why the MCP tools are better than alternatives
3. **Be concise** - AI assistants have context limits; focus on essential guidance

Example ``INSTRUCTIONS.md``:

.. code-block:: markdown

    ## My Extension

    Use MCP tools instead of CLI for better results:

    | Instead of...              | Use                    |
    |----------------------------|------------------------|
    | `my-cli command`           | `my-tool`              |
    | `my-cli search "term"`     | `my-search` with term  |

    ### Benefits
    - Structured output that AI can parse
    - Better error handling and context
    - Integrated with project configuration

Security
~~~~~~~~

Discovered extensions are managed in ``mate/extensions.php``:

- The ``discover`` command automatically adds discovered extensions
- All extensions default to ``enabled: true`` when discovered
- Set ``enabled: false`` to disable an extension
- Set ``extra.ai-mate.extension`` to ``false`` to keep a package out of discovery entirely

Troubleshooting
---------------

Extensions Not Discovered
~~~~~~~~~~~~~~~~~~~~~~~~~

If your extensions aren't being found:

1. **Verify composer.json configuration**:

   Ensure your package has the ``extra.ai-mate`` section:

   .. code-block:: json

       {
           "extra": {
               "ai-mate": {
                   "scan-dirs": ["src"]
               }
           }
       }

2. **Run discovery**:

   .. code-block:: terminal

       $ vendor/bin/mate discover

   If the host project has already been initialized, Composer install/update should also refresh
   discovery automatically.

3. **Check the extensions file**:

   .. code-block:: terminal

       $ cat mate/extensions.php

   Verify your package is listed and ``enabled`` is ``true``.

   If the package intentionally sets ``extra.ai-mate.extension`` to ``false``, it will not appear
   in ``mate/extensions.php``.

Extensions Not Loading
~~~~~~~~~~~~~~~~~~~~~~

If extensions are discovered but not loading:

1. **Check enabled status** in ``mate/extensions.php``::

       return [
           'vendor/my-extension' => ['enabled' => true],  // Must be true
       ];

2. **Verify scan directories exist** and contain PHP files with MCP attributes.

3. **Check for PHP errors** in your extension code:

   .. code-block:: terminal

       $ php -l src/MyTool.php

Tools Not Appearing
~~~~~~~~~~~~~~~~~~~

If your MCP tools don't appear in the AI assistant:

1. **Verify MCP attributes** are correctly applied::

       use Mcp\Capability\Attribute\McpTool;

       class MyTool
       {
           #[McpTool(name: 'my-tool', description: 'Description here')]
           public function execute(): string
           {
               return 'result';
           }
       }

2. **Check that classes are in scan directories** defined in ``composer.json``.

3. **Restart your AI assistant** after making changes.

4. **Check server logs** for registration errors.

Tool Execution Fails
~~~~~~~~~~~~~~~~~~~~

If tools are visible but fail when called:

1. **Check return types** - tools must return scalar values or arrays::

       // Good
       public function execute(): string { return 'result'; }
       public function execute(): array { return ['key' => 'value']; }

       // Bad - objects are not directly serializable
       public function execute(): object { return new stdClass(); }

2. **Check for exceptions** in your tool code.

3. **Verify dependencies** are properly injected.

Dependency Injection Issues
~~~~~~~~~~~~~~~~~~~~~~~~~~~

If dependencies aren't being injected:

1. **Register services** in your ``services.php`` or ``config/services.php``::

       $services->set(MyService::class)
           ->autowire()
           ->autoconfigure();

2. **Check interface bindings**::

       $services->alias(MyInterface::class, MyImplementation::class);

3. **Verify service configuration** is listed in ``composer.json``:

   .. code-block:: json

       {
           "extra": {
               "ai-mate": {
                   "includes": ["config/services.php"]
               }
           }
       }

Agent Instructions Not Loading
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If your agent instructions aren't being provided to AI assistants:

1. **Verify the file exists** at the path specified in ``composer.json``

2. **Check the path is correct** - must be relative to package root:

   .. code-block:: json

       {
           "extra": {
               "ai-mate": {
                   "instructions": "INSTRUCTIONS.md"
               }
           }
       }

3. **Ensure the file is readable** and contains valid markdown

4. **Use debug command** to verify discovery:

   .. code-block:: terminal

       $ vendor/bin/mate debug:extensions

   Look for ``instructions`` field in the output.

For general server issues and debugging tips, see the :doc:`troubleshooting` guide.
