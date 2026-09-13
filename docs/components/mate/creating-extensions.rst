Creating Mate Extensions
========================

Mate extensions are Composer packages that declare themselves using a specific configuration
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
            "symfony/ai-mate": "^0.13"
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

2. Create Capabilities
~~~~~~~~~~~~~~~~~~~~~~

Mark public methods with the native Mate attributes. Mate finds them by reflection and derives
the JSON input schema from the method signature plus the ``@param`` PHPDoc::

    use Psr\Log\LoggerInterface;
    use Symfony\AI\Mate\Attribute\MateTool;

    class MyTool
    {
        // Dependencies are automatically injected
        public function __construct(
            private LoggerInterface $logger,
        ) {
        }

        /**
         * @param string $param The value to process
         */
        #[MateTool(name: 'my-tool', title: 'My Tool', description: 'What this tool does')]
        public function execute(string $param): string
        {
            $this->logger->info('Tool executed', ['param' => $param]);

            return 'Result: ' . $param;
        }
    }

Three attributes are available, all in ``Symfony\AI\Mate\Attribute``:

``#[MateTool]``
    A method the agent calls with arguments. Parameters: ``name``, ``title``, ``description``.

``#[MateResource]``
    Data the agent addresses by a fixed URI. Parameters: ``uri``, ``name``, ``title``,
    ``description``, ``mimeType``.

``#[MateResourceTemplate]``
    Data addressed by a URI pattern; the variables of ``uriTemplate`` are passed to the method.
    Parameters: ``uriTemplate``, ``name``, ``title``, ``description``, ``mimeType``.

.. note::

    These are Mate's own attributes and unrelated to the Agent component's
    ``Symfony\AI\Agent\Toolbox\Attribute\AsTool``.

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

Tools and resources support constructor dependency injection via Symfony's DI Container.
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
- Content is aggregated into ``mate/AGENT_INSTRUCTIONS.md`` and the managed block in ``AGENTS.md``

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

Skills
~~~~~~

``extra.ai-mate.skills`` (optional)

- List of directories holding `Agent Skills <https://agentskills.io>`_, relative to package root
  (a single string is also accepted)
- Each immediate subdirectory is one skill and must contain a ``SKILL.md`` file
- Conventionally a single ``skills`` directory
- When a project runs ``mate discover`` (or ``mate skills:install``), each skill directory is
  installed into the project's ``.agents/skills/`` and symlinked into ``.claude/skills/`` so coding
  agents can use it

Example configuration:

.. code-block:: json

    {
        "extra": {
            "ai-mate": {
                "scan-dirs": ["src"],
                "skills": ["skills"]
            }
        }
    }

The directory layout for the example above::

    skills/
    └── my-skill/
        ├── SKILL.md
        └── references/
            └── details.md

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
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Agent instructions help AI assistants understand when and how to use your extension's tools.
A good ``INSTRUCTIONS.md`` file should:

1. **Map existing commands to your tools** - Show what tools replace common CLI operations
2. **Highlight benefits** - Explain why your tools are better than the alternatives
3. **Be concise** - AI assistants have context limits; focus on essential guidance

Example ``INSTRUCTIONS.md``:

.. code-block:: markdown

    ## My Extension

    Use the Mate tools instead of the CLI for better results:

    | Instead of...              | Use                                                    |
    |----------------------------|--------------------------------------------------------|
    | `my-cli command`           | `vendor/bin/mate tools:call my-tool`                   |
    | `my-cli search "term"`     | `vendor/bin/mate tools:call my-search --term="term"`   |

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

2. **Verify scan directories exist** and contain PHP files with Mate attributes.

3. **Check for PHP errors** in your extension code:

   .. code-block:: terminal

       $ php -l src/MyTool.php

Tools Not Appearing
~~~~~~~~~~~~~~~~~~~

If your tools don't appear:

1. **Verify the Mate attributes** are correctly applied::

       use Symfony\AI\Mate\Attribute\MateTool;

       class MyTool
       {
           #[MateTool(name: 'my-tool', description: 'Description here')]
           public function execute(): string
           {
               return 'result';
           }
       }

2. **Check that classes are in scan directories** defined in ``composer.json``.

3. **Confirm the class is autoloadable** - Mate resolves the class name from the file and skips
   the file when the class cannot be loaded. Run ``composer dump-autoload`` after adding it.

4. **List what Mate actually found**::

       $ vendor/bin/mate tools:list
       $ vendor/bin/mate debug:capabilities --extension=vendor/my-extension

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

For general issues and debugging tips, see the :doc:`troubleshooting` guide.
