Creating MCP Extensions
=======================

MCP extensions are Composer packages that declare themselves using a specific configuration
in ``composer.json``, similar to PHPStan extensions.

Quick Start
-----------

1. Configure composer.json
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: json

    {
        "name": "vendor/my-extension",
        "type": "library",
        "require": {
            "symfony/ai-mate": "^0.1"
        },
        "extra": {
            "ai-mate": {
                "scan-dirs": ["src", "lib"],
                "instructions": "INSTRUCTIONS.md"
            }
        }
    }

The ``extra.ai-mate`` section is required for your package to be discovered as an extension.

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

Extensions must be explicitly enabled in ``mate/extensions.php``:

- The ``discover`` command automatically adds discovered extensions
- All extensions default to ``enabled: true`` when discovered
- Set ``enabled: false`` to disable an extension

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

3. **Check the extensions file**:

   .. code-block:: terminal

       $ cat mate/extensions.php

   Verify your package is listed and ``enabled`` is ``true``.

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
