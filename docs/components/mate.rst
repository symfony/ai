Symfony AI - Mate Component
===========================

The Mate component provides a command-line assistant (``vendor/bin/mate``) that lets coding
agents inspect a running PHP application (including Symfony) through project-aware tools.
This is a development tool, not intended for production use.

Installation
------------

.. code-block:: terminal

    $ composer require --dev symfony/ai-mate

Purpose
-------

Symfony AI Mate is a **development tool** that gives your coding agent (Claude Code, Codex,
GitHub Copilot, Cursor, JetBrains AI, etc.) specific knowledge about your PHP application and
development environment.

Mate is a plain CLI: the agent runs ``vendor/bin/mate tools:call …`` the way it runs any other
command. There is no server to start, no client-specific configuration file, and no permanent
tool descriptions occupying the agent's context window. Any agent that can run a shell command
can use Mate.

Mate reads your application without booting it: the compiled container is parsed from the dumped
XML, the profiler and logs are read from disk. That means it still answers when the application
itself does not boot, which is usually the moment you need it.

**Important**: This is intended for development and debugging only, not for production
deployment.

This is the core package. It works with any PHP application - while it includes
Symfony-specific tools via bridges, the core functionality is framework-agnostic.

Quick Start
-----------

Install with composer:

.. code-block:: terminal

    $ composer require --dev symfony/ai-mate

Initialize configuration:

.. code-block:: terminal

    $ vendor/bin/mate init

This creates:

* ``mate/`` directory with configuration files
* ``mate/src`` directory for custom tools
* ``mate/AGENT_INSTRUCTIONS.md`` placeholder (refreshed by ``mate discover``)
* a managed instruction block in ``AGENTS.md``
* ``CLAUDE.md`` importing ``AGENTS.md`` via ``@AGENTS.md``, so Claude Code picks the
  instructions up

It also updates your ``composer.json`` with the following configuration:

.. code-block:: json

    {
        "autoload-dev": {
            "psr-4": {
                "Mate\\": "mate/src/"
            }
        },
        "extra": {
            "ai-mate": {
                "extension": false,
                "scan-dirs": ["mate/src"],
                "includes": ["mate/config.php"]
            }
        }
    }

The ``extension: false`` flag prevents your application from being discovered as a reusable Mate
extension when it is installed as a dependency elsewhere. Remove it, or set it to ``true``, only
if your package should be discoverable by other projects.

After running ``mate init``, update your autoloader:

.. code-block:: terminal

    $ composer dump-autoload

Finally, install the skills:

.. code-block:: terminal

    $ vendor/bin/mate skills:install

This writes the ``SKILL.md`` files of every enabled extension into ``.agents/skills/``, with a
mirror in ``.claude/skills/``. Do not skip this step. The instructions above tell an agent that
Mate exists, but the skills are what tell it *when* to reach for which tool, and they are the file
an agent trips over on its own while looking around a fresh checkout. ``mate discover`` runs the
install for you, and so does Composer after ``composer require``, so in practice you rarely call
it by hand. See `Skills`_.

Automatic Discovery
-------------------

The ``symfony/ai-mate`` package installs the optional Composer plugin
``symfony/ai-mate-composer-plugin``. After your project has been initialized and
``mate/extensions.php`` exists, Composer automatically runs::

    $ vendor/bin/mate discover --composer

after ``composer install`` and ``composer update``. This refreshes discovered extensions and
regenerates the managed instruction artifacts.

Before initialization, the Composer plugin does not modify your project. It only prints a hint to run::

    $ vendor/bin/mate init

Use ``vendor/bin/mate discover`` whenever you want to refresh extensions manually after changing
Mate configuration, adding instructions, or working on local extensions.

Discover available extensions:

.. code-block:: terminal

    $ vendor/bin/mate discover

This command also refreshes:

* ``mate/AGENT_INSTRUCTIONS.md``
* Managed AI Mate instruction section in ``AGENTS.md``

Using Mate from a coding agent
------------------------------

There is nothing to start. Your agent discovers Mate through the instructions written into
``AGENTS.md`` and ``mate/AGENT_INSTRUCTIONS.md``, and runs four commands:

.. code-block:: terminal

    $ vendor/bin/mate tools:list                          # what is available
    $ vendor/bin/mate tools:inspect symfony-profiler-list # parameters and JSON input schema
    $ vendor/bin/mate tools:call symfony-profiler-list --limit=1
    $ vendor/bin/mate resources:read symfony-profiler://profile/<token>

Tool parameters are passed as long options, one per parameter, and coerced to the parameter's
declared type. Booleans may be passed as a bare flag, and a variadic parameter collects the option
repeated. For nested or associative values, and for parameter names that collide with a console
option, pass a JSON object instead:

.. code-block:: terminal

    $ vendor/bin/mate tools:call monolog-search --term="^GET" --regex
    $ vendor/bin/mate tools:call some-tool --tag=a --tag=b
    $ vendor/bin/mate tools:call some-tool --json='{"filters": {"level": "error"}}'

All four accept ``--format``. Use ``--format=json`` when the result is parsed, and
``--format=toon`` (requires ``helgesverre/toon``) for the smallest context footprint.

.. note::

    ``mate init`` asks which command your agent should use and records it as ``mate.invocation``
    in ``mate/config.php``, together with the PHP version that command runs under. See
    `Choosing the interpreter`_.

Add Custom Tools
----------------

The easiest way to add tools is to create a ``mate/src`` folder next to your ``src`` and ``tests``
directories, then add a class with a public method carrying the ``#[MateTool]`` attribute::

    // mate/src/MyTool.php
    namespace Mate;

    use Symfony\AI\Mate\Attribute\MateTool;

    class MyTool
    {
        /**
         * @param string $param The value to process
         */
        #[MateTool(name: 'my-tool', title: 'My Tool', description: 'My custom tool')]
        public function execute(string $param): array
        {
            return ['result' => $param];
        }
    }

Mate discovers the method by reflection and derives its JSON input schema from the signature plus
the ``@param`` PHPDoc, so the description you write there is what the agent sees in
``tools:inspect``.

Two further attributes cover data the agent navigates into rather than calls:

* ``#[MateResource]`` marks a method as a static resource, addressed by a fixed ``uri``.
* ``#[MateResourceTemplate]`` marks a method as a templated resource; the variables in its
  ``uriTemplate`` are passed to the method.

::

    use Symfony\AI\Mate\Attribute\MateResource;
    use Symfony\AI\Mate\Attribute\MateResourceTemplate;

    class MyResources
    {
        #[MateResource(uri: 'my-app://config', name: 'config', mimeType: 'application/json')]
        public function config(): string
        {
            return json_encode(['debug' => true]);
        }

        #[MateResourceTemplate(uriTemplate: 'my-app://entity/{id}', name: 'entity')]
        public function entity(string $id): array
        {
            return ['id' => $id];
        }
    }

Resources are read with ``vendor/bin/mate resources:read <uri>``. The split is worth keeping in
mind when designing your own: a tool is something the agent *calls* with arguments, a resource is
something it *addresses* and can drill into, which keeps large payloads out of the context window
until they are actually needed.

After adding a class under ``mate/src/``, run ``composer dump-autoload`` if the autoloader does not
know it yet. Verify with ``vendor/bin/mate tools:list``.

Choosing the interpreter
------------------------

Mate reads the compiled container, the profiler cache and the logs of *this* project, and
extensions may behave differently per runtime. Running it under the wrong interpreter therefore
does not just fail, it reports on something that is not the application under test.

``mate init`` asks which command your coding agent should use and writes two parameters into
``mate/config.php``::

    $container->parameters()
        ->set('mate.invocation', 'ddev exec vendor/bin/mate')
        ->set('mate.php_version', '8.3')
    ;

``mate.invocation``
    The full command the agent must use, wrapper included. It is materialized into
    ``mate/AGENT_INSTRUCTIONS.md`` and the managed ``AGENTS.md`` block, so the prefix ends up where
    the agent actually reads it. When a ``.ddev/`` directory is present, ``mate init`` proposes
    ``ddev exec vendor/bin/mate`` as the default. Answering with a wrapper alone is enough,
    ``symfony php`` is recorded as ``symfony php vendor/bin/mate``. Left at its default, the value
    is the plain ``vendor/bin/mate`` and nothing is prefixed anywhere.

``mate.php_version``
    The PHP version the project runs on, recorded as ``major.minor``. When ``mate.invocation``
    wraps the binary, ``mate init`` runs ``php`` through that wrapper to find out which
    interpreter it really reaches, rather than recording the one that happened to run ``init``.
    If the wrapper cannot be reached, ``init`` warns and falls back to the current process, and
    you should check the value by hand. Mate refuses to start under a
    different one and points at ``mate.invocation`` in the error:

    .. code-block:: terminal

        $ vendor/bin/mate tools:list

         [ERROR] Mate is running under PHP 8.4.15 but this project expects PHP 8.3.
                 Run it as "ddev exec vendor/bin/mate". ...

    Set the parameter to ``null`` to disable the check.

    ``init``, ``list``, ``help`` and ``completion`` stay callable under any interpreter, because
    ``init`` is the command that writes this configuration in the first place and the others never
    read the application. They print a warning instead, so a wrong interpreter is visible before it
    reaches a command that does refuse.

Configuration
-------------

The configuration folder is called ``mate`` and is located in your project's root directory.
It contains two important files:

* ``mate/extensions.php`` - Enable/disable extensions
* ``mate/config.php`` - Configure settings

.. tip::

    The folder and default configuration is automatically generated by running ``mate init``.

Extensions Configuration
~~~~~~~~~~~~~~~~~~~~~~~~

``mate/extensions.php`` records which extensions are enabled, plus the state of every Agent Skill
they ship. Mate maintains it for you, so reach for a command before the editor:

.. code-block:: terminal

    $ vendor/bin/mate discover        # register newly installed extensions and install their skills
    $ vendor/bin/mate skills:install  # rebuild the generated skill folders from the recorded state
    $ vendor/bin/mate skills:list     # show which skills are enabled and how they are installed
    $ vendor/bin/mate skills:validate # check the generated folders against the recorded state

Editing the file is for the settings that express your intent: whether an extension is enabled, and
the ``enabled`` and ``mode`` keys of a skill (see `Skills`_). Every other key is rewritten on the
next install::

    // mate/extensions.php
    // This file is managed by Mate - use `discover` or `skills:*` commands
    // over manual editing. Only changes to `mode` or `enabled` are kept,
    // every other key is overwritten by Mate.

    return [
        'vendor/package-name' => ['enabled' => true],
        'vendor/another-package' => ['enabled' => false],
    ];

Services Configuration
~~~~~~~~~~~~~~~~~~~~~~

::

    // mate/config.php
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    return static function (ContainerConfigurator $container): void {
        $container->parameters()
            // Override default parameters here
            // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
            // ->set('mate.env_file', ['.env'])
        ;

        $container->services()
            // Register your custom services here
        ;
    };

Disabling Specific Features
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Use the MateHelper class to disable specific features::

    use Symfony\AI\Mate\Container\MateHelper;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    return static function (ContainerConfigurator $container): void {
        MateHelper::disableFeatures($container, [
            'symfony/ai-mate' => ['server-info'],
        ]);
    };

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

Use ``%env(VAR_NAME)%`` syntax in service configuration to reference environment variables.
See the `Symfony documentation on environment variables`_ for more information.

.. _`Symfony documentation on environment variables`: https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables

Adding Third-Party Extensions
-----------------------------

1. Install the package:

   .. code-block:: terminal

       $ composer require vendor/symfony-tools

2. Discover available tools (auto-generates/updates ``mate/extensions.php``):

   .. code-block:: terminal

       $ vendor/bin/mate discover

   When the project has already been initialized, Composer also refreshes discovery automatically
   after ``composer install`` and ``composer update``.

3. Optionally disable specific extensions::

       // mate/extensions.php
       return [
           'vendor/symfony-tools' => ['enabled' => true],
           'vendor/unwanted-tools' => ['enabled' => false],
       ];

To create a third party extension, see :doc:`mate/creating-extensions`.

Available Bridges
-----------------

Symfony Bridge
~~~~~~~~~~~~~~

The Symfony bridge (``symfony/ai-symfony-mate-extension``) provides container introspection and
profiler data access tools for Symfony applications.

Container Introspection
^^^^^^^^^^^^^^^^^^^^^^^

**Tools:**

* ``symfony-services`` - Search services in the compiled container, filtered by service ID,
  class name or tag. Returns the matches under ``services`` together with ``count`` and
  ``truncated``; at most ``limit`` entries (default 100) are listed, so narrow the filter rather
  than raising the limit. Both this tool and the next one fail loudly when no container has been
  dumped yet, instead of answering as if nothing matched
* ``symfony-service-detail`` - Show one service by its exact ID, including class, tags, method
  calls and constructor or factory information

**Configuration:**

Single cache directory (default)::

    $container->parameters()
        ->set('ai_mate_symfony.cache_dir', '%mate.root_dir%/var/cache');

Multiple directories with contexts (e.g., for `multi-kernel applications`_ that split their
cache per ``APP_ID``)::

    $container->parameters()
        ->set('ai_mate_symfony.cache_dir', [
            'website' => '%mate.root_dir%/var/cache/website',
            'admin' => '%mate.root_dir%/var/cache/admin',
        ]);

When using multiple directories, ``symfony-services`` returns the services grouped by context and
``symfony-service-detail`` includes the ``context`` a service was found in. Both tools accept an
optional ``context`` parameter to narrow the lookup to a single kernel.

**Troubleshooting:**

*Container not found*:

Ensure the cache directory parameter points to the correct location. The bridge looks for
compiled container XML files in the cache directory, including kernels with custom class names.

*Services not appearing*:

1. Clear Symfony cache: ``bin/console cache:clear``
2. Ensure the container is compiled (warm up cache)
3. Verify the container XML file exists in the cache directory

Profiler Data Access
^^^^^^^^^^^^^^^^^^^^

When ``symfony/http-kernel`` and ``symfony/web-profiler-bundle`` are installed, profiler tools
become available for accessing Symfony profiler data.

**Tools:**

* ``symfony-profiler-list`` - List available profiler profiles with summary data, supports filtering by date range (``from``/``to`` parameters) and limiting results (use ``limit: 1`` for the latest profile)
* ``symfony-profiler-get`` - Get a specific profile by token

All tools return profiles with a ``resource_uri`` field that points to the full profile resource.

**Resources:**

* ``symfony-profiler://profile/{token}`` - Full profile details including metadata and list of available collectors with URIs
* ``symfony-profiler://profile/{token}/{collector}`` - Detailed collector-specific data (request, response, exception, events, etc.)

When the related dependencies are installed, collector data is normalized for AI consumption for:

* Doctrine DBAL queries
* Symfony Mailer messages
* Symfony Translation usage

If no formatter is registered for a collector, Mate falls back to exposing the collector's raw data.

**Configuration:**

Single profiler directory (default)::

    $container->parameters()
        ->set('ai_mate_symfony.profiler_dir', '%mate.root_dir%/var/cache/dev/profiler');

Multiple directories with contexts (e.g., for `multi-kernel applications`_)::

    $container->parameters()
        ->set('ai_mate_symfony.profiler_dir', [
            'website' => '%mate.root_dir%/var/cache/website/dev/profiler',
            'admin' => '%mate.root_dir%/var/cache/admin/dev/profiler',
        ]);

When using multiple directories, profiles include a ``context`` field for filtering.

**Example Usage:**

.. code-block:: terminal

    # Find the failed requests
    $ vendor/bin/mate tools:call symfony-profiler-list --statusCode=500 --limit=20

    # See which collectors that profile actually has
    $ vendor/bin/mate resources:read symfony-profiler://profile/abc123

    # Read one of them
    $ vendor/bin/mate resources:read symfony-profiler://profile/abc123/exception

**Security:**

Cookies, session data, authentication headers, and sensitive environment variables are automatically
redacted from profiler data.

**Extensibility:**

Create custom collector formatters by implementing ``CollectorFormatterInterface`` and
registering via DI tag ``ai_mate_symfony.profiler_collector_formatter``.

**Troubleshooting:**

*Profiles not found*:

1. Ensure the profiler directory parameter points to the correct location
2. Verify Symfony profiler is enabled in your environment
3. Generate some HTTP requests to create profile data

*Collector data not available*:

1. Check that the specific collector is enabled in Symfony profiler configuration
2. Verify the profile was captured with that collector active

Monolog Bridge
~~~~~~~~~~~~~~

The Monolog bridge (``symfony/ai-monolog-mate-extension``) provides log search and analysis tools:

* ``monolog-search`` - Search log entries by text term with optional filters (supports ``regex`` parameter for regex patterns and ``level`` filter)
* ``monolog-context-search`` - Search logs by context field value
* ``monolog-tail`` - Get the last N log entries
* ``monolog-list-files`` - List available log files
* ``monolog-list-channels`` - List all log channels

Single log directory (default)::

    $container->parameters()
        ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/log');

Multiple directories with contexts (e.g., for `multi-kernel applications`_ that split their
logs per ``APP_ID``)::

    $container->parameters()
        ->set('ai_mate_monolog.log_dir', [
            'website' => '%mate.root_dir%/var/log/website',
            'admin' => '%mate.root_dir%/var/log/admin',
        ]);

When using multiple directories, log entries and files carry a ``kernel_context`` field, and all
Monolog tools accept an optional ``kernelContext`` parameter to restrict the lookup to a single
kernel. The field is named ``kernel_context`` rather than ``context`` to keep it apart from the
Monolog context of a log record.

**Troubleshooting**

*Logs not found*:

Ensure the log directory parameter points to the correct location where your Monolog
log files are stored.

*Log parsing errors*:

1. Verify log format is standard Monolog line format or JSON
2. Check file permissions on log files
3. Ensure log files are not empty or corrupted

Built-in Tools
--------------

The core package provides basic system information tools:

* ``server-info`` - Get PHP runtime environment details: version, OS, OS family, and loaded extensions

Skills
------

`Agent Skills <https://agentskills.io>`_ are ``SKILL.md`` files that give your coding agent
structured, multi-step "how-to" knowledge for a task. Extensions can ship skills alongside their
tools, and Mate installs them onto the filesystem where coding agents read them.

Skills are what make the CLI findable. A tool that exists but is never invoked is worth nothing,
and a bare CLI is invisible to an agent that was never told about it. The skills describe when to
reach for which tool and in what order, which is the difference between Mate being installed and
Mate being used.

You usually do not run anything: ``mate discover`` (which also runs automatically after
``composer require``) installs the skills of every enabled extension. To sync them manually, use
``mate skills:install``.

Each skill is installed under a ``mate-`` prefixed directory name (e.g. ``mate-demo-skill``) to
avoid clashing with skills you maintain from other sources; the ``name`` in the installed
``SKILL.md`` is rewritten to match. Skills land in two locations:

* ``.agents/skills/`` is the source of truth, read directly by Codex, OpenCode and GitHub Copilot.
* ``.claude/skills/`` mirrors ``.agents/skills/`` via relative symlinks for Claude Code, which only
  reads its own directory.

Both folders are generated output: ``skills:install`` is an idempotent reconciler that rebuilds them
from source on every run and prunes skills that are gone or disabled. Do not edit them by hand, your
changes are overwritten on the next run and reported as errors by ``mate skills:validate``.

Skills are **copied, never symlinked into** ``vendor/``. What your agent loads is a real file you can
open and diff, and a package update cannot silently change it underneath you. Mate does not touch
your ``.gitignore``: committing the generated folders is recommended, because it turns an upstream
skill change into a reviewable diff instead of something that lands silently.

All skill state lives in ``mate/extensions.php``. Two keys per skill carry your intent:

* ``enabled`` controls whether the skill is installed at all. Use ``mate skills:disable <name>`` and
  ``mate skills:enable <name>`` to flip it.
* ``mode`` is either ``managed``, where Mate builds the skill from the package, or ``override``,
  which hands ownership to you: Mate then builds from your own ``mate/skills/<name>/`` copy and
  never writes into ``mate/skills/``. Use ``mate skills:override <name>`` to switch, and
  ``mate skills:reset <name>`` to hand the skill back.

The ``skills:*`` commands set both for you, which is the recommended way to change them, and they also
reinstall, so the recorded state below never falls out of step with your intent. Editing the two keys
by hand works as well; the next install picks the change up.

Everything else is written by Mate and rewritten on every install; the resulting ``state``
(``managed``, ``override`` or ``disabled``), the ``source`` it was built from, the ``source_hash``
and ``hash`` pair used to detect drift, and the generated ``targets``::

    // mate/extensions.php
    return [
        'vendor/package' => [
            'enabled' => true,
            'skills' => [
                'demo-skill' => [
                    'enabled' => true,
                    'mode' => 'managed',
                    'state' => 'managed',
                    'source' => 'vendor/vendor/package/skills/demo-skill',
                    'source_hash' => 'sha256:...',
                    'hash' => 'sha256:...',
                    'targets' => [
                        '.agents/skills/mate-demo-skill',
                        '.claude/skills/mate-demo-skill',
                    ],
                ],
            ],
        ],
    ];

Use ``mate skills:list`` for an overview, and ``mate skills:validate`` to check the generated folders
against that record: it reports hand-edited content, missing folders, and sources that moved on since
the last install. It also looks at the installed content itself: it warns when a Markdown link points
at a file that is not part of the skill, and suggests a better description when it is too short or
never says when the skill applies, because that description is all an agent has when it decides
whether to load the skill. Those description findings are suggestions, not warnings: they are printed
but never change the exit code, not even with ``--strict``. ``mate skills:prune`` removes leftover
``mate-*`` folders.

To see what an install would do before it does it, run ``mate skills:install --dry-run``: the same
reconciler runs and reports what it would install, rebuild or remove, but nothing is written.

A skill ships with the extension whose tools it drives, so a project only ever installs skills it
can actually follow. The core package ships two:

``php-environment-check``
    Establish whether a failing tool is the PHP runtime rather than the application.

``system-information``
    Resolve which dependency versions are actually installed, via ``composer show`` and
    ``composer.lock``.

The Symfony extension adds three:

``symfony-profiler-debugging``
    Diagnose a request that errored or was slow, through the profiler: which profile to find, which
    collectors to read, and in what order depending on the symptom.

``symfony-request-triage``
    Decide which of the other skills a given symptom calls for.

``symfony-service-inspection``
    Inspect the compiled DI container when the wiring, not the code, is the suspect.

And the Monolog extension one:

``symfony-log-investigation``
    Investigate trends across requests in the Monolog log files, rather than one failed request.

Commands
--------

``mate init``
    Initialize AI Mate configuration and create the ``mate/`` directory.

``mate discover``
    Scan for Mate extensions in installed packages. This command will:

    - Scan your vendor directory for packages with ``extra.ai-mate`` configuration
    - Generate or update ``mate/extensions.php`` with discovered extensions
    - Preserve existing enabled/disabled states for known extensions
    - Default new extensions to enabled
    - Install Agent Skills shipped by enabled extensions (see `Skills`_)

``mate skills:install``
    Install the Agent Skills shipped by your enabled extensions so your coding agent can use
    them. This runs automatically as part of ``mate discover``; use it for an explicit re-sync.
    Pass ``--dry-run`` to see what a run would install, rebuild or remove without writing
    anything. See `Skills`_.

``mate skills:list``
    List declared and installed skills with their enabled, mode, state and status information.
    Read-only diagnostic. See `Skills`_.

``mate skills:validate``
    Check the generated skill folders against the state recorded in ``mate/extensions.php``, and the
    installed content itself for dead links and descriptions an agent cannot act on. Exits with a
    non-zero status when a skill is broken; pass ``--strict`` to fail on warnings too. Suggestions
    about a description never affect the exit code. Read-only. See `Skills`_.

``mate skills:prune``
    Remove generated ``mate-*`` folders that no longer belong to any skill. Pass ``--dry-run`` to
    see what would be removed. See `Skills`_.

``mate skills:override <name>``
    Take ownership of a skill: copy the package's version into ``mate/skills/<name>/`` and switch it
    to ``'mode' => 'override'``. Accepts the installed (``mate-…``) or the original name. Pass
    ``--force`` to replace an existing copy. See `Skills`_.

``mate skills:reset <name>``
    Hand an overridden skill back to Mate, so it is built from the package again. Your copy under
    ``mate/skills/<name>/`` is kept unless you pass ``--delete-copy``. See `Skills`_.

``mate skills:disable <name>``
    Hide a skill from coding agents: remove its generated folders and record it as disabled. The
    entry stays in ``mate/extensions.php``, and a copy of your own under ``mate/skills/`` is left
    untouched. See `Skills`_.

``mate skills:enable <name>``
    Make a disabled skill visible again and rebuild its generated folders. See `Skills`_.

``mate clear-cache``
    Clear the cache.

``mate debug:capabilities``
    Display all discovered capabilities grouped by extension. This command is useful for:

    - Verifying extension installation and capability registration
    - Debugging missing or misconfigured extensions
    - Understanding which package provides each capability
    - Inspecting available tools during development

    **Options:**

    ``--format=FORMAT``
        Output format: ``text`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    ``--extension=EXTENSION``
        Filter by extension package name (e.g., ``symfony/ai-monolog-mate-extension``)

    ``--type=TYPE``
        Filter by capability type: ``tool``, ``resource``, or ``template``

    **Examples:**

    .. code-block:: terminal

        # Show all capabilities
        $ vendor/bin/mate debug:capabilities

        # Show only tools
        $ vendor/bin/mate debug:capabilities --type=tool

        # Show capabilities from specific extension
        $ vendor/bin/mate debug:capabilities --extension=symfony/ai-monolog-mate-extension

        # JSON output for scripting
        $ vendor/bin/mate debug:capabilities --format=json

        # TOON output for token-efficient inspection
        $ vendor/bin/mate debug:capabilities --format=toon

        # Root project capabilities
        $ vendor/bin/mate debug:capabilities --extension=_custom

``mate debug:extensions``
    Display detailed information about discovered and loaded Mate extensions. This command is useful for:

    - Understanding which extensions are discovered vs enabled vs loaded
    - Debugging extension loading issues
    - Verifying extension configuration from ``mate/extensions.php``
    - Inspecting scan directories and include files
    - Troubleshooting why an extension isn't providing capabilities

    **Status Indicators:**

    ``[enabled]``
        Extension is configured to load in ``mate/extensions.php``

    ``[loaded]``
        Extension successfully loaded into the DI container

    ``[not loaded]``
        Extension failed to load (package removed, error, etc.) - useful for troubleshooting

    **Options:**

    ``--format=FORMAT``
        Output format: ``text`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    ``--show-all``
        Show all discovered extensions including disabled ones

    **Examples:**

    .. code-block:: terminal

        # Show enabled extensions
        $ vendor/bin/mate debug:extensions

        # Show all extensions (including disabled)
        $ vendor/bin/mate debug:extensions --show-all

        # JSON output for scripting
        $ vendor/bin/mate debug:extensions --format=json

        # TOON output for token-efficient inspection
        $ vendor/bin/mate debug:extensions --format=toon

``mate tools:list``
    List all available tools with their metadata. This command provides a compact
    overview of tools for quick reference and filtering.

    **Options:**

    ``--filter=PATTERN``
        Filter tools by name pattern (supports wildcards like ``search*`` or ``*logs``)

    ``--extension=EXTENSION``
        Filter tools by extension package name

    ``--format=FORMAT``
        Output format: ``table`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    **Examples:**

    .. code-block:: terminal

        # List all tools
        $ vendor/bin/mate tools:list

        # Filter by name pattern
        $ vendor/bin/mate tools:list --filter="monolog*"
        $ vendor/bin/mate tools:list --filter="*search"

        # Show tools from specific extension
        $ vendor/bin/mate tools:list --extension=symfony/ai-monolog-mate-extension

        # JSON output for scripting
        $ vendor/bin/mate tools:list --format=json

        # Combined filters
        $ vendor/bin/mate tools:list --extension=symfony/ai-monolog-mate-extension --filter="*search"

``mate tools:inspect``
    Display detailed information about a specific tool including its full JSON input schema.
    This command is useful for understanding tool parameters and requirements.

    **Arguments:**

    ``tool-name``
        Name of the tool to inspect (required)

    **Options:**

    ``--format=FORMAT``
        Output format: ``text`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    **Examples:**

    .. code-block:: terminal

        # Inspect a specific tool
        $ vendor/bin/mate tools:inspect server-info

        # Inspect extension tool
        $ vendor/bin/mate tools:inspect monolog-search

        # JSON output for scripting
        $ vendor/bin/mate tools:inspect server-info --format=json

``mate tools:call``
    Execute a tool, passing each of its parameters as a long option.

    **Arguments:**

    ``tool-name``
        Name of the tool to execute (required)

    **Options:**

    ``--<param>=<value>``
        One option per tool parameter. Values are coerced to the parameter's declared type.
        A boolean parameter may be passed as a bare ``--<flag>``. Repeat the option to pass
        several values to a variadic parameter; repeating it on a single-value parameter is an
        error rather than a silent last-one-wins.

    ``--json=JSON``
        Tool parameters as a JSON object, merged under any ``--<param>`` options. Use this for
        nested or associative values, and for parameter names shadowed by a console option
        (``format``, ``json``, ``help``, ``silent``, ``quiet``, ``verbose``, ``version``,
        ``ansi``, ``no-ansi``, ``no-interaction``).

    ``--format=FORMAT``
        Output format: ``pretty`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    **Examples:**

    .. code-block:: terminal

        # Execute a tool without parameters
        $ vendor/bin/mate tools:call server-info

        # Execute a tool with parameters
        $ vendor/bin/mate tools:call monolog-search --term=error --level=error

        # Boolean parameters may be passed as bare flags
        $ vendor/bin/mate tools:call monolog-search --term="^GET" --regex

        # Complex or array parameters
        $ vendor/bin/mate tools:call some-tool --json='{"tags": ["a", "b"]}'

        # JSON output format
        $ vendor/bin/mate tools:call server-info --format=json

        # TOON output for token-efficient inspection
        $ vendor/bin/mate tools:call server-info --format=toon

``mate resources:read``
    Read a resource by its URI. Matches both static resources and resource templates; for a
    template, the variables in the URI are passed to the handler.

    **Arguments:**

    ``uri``
        URI of the resource to read (required)

    **Options:**

    ``--format=FORMAT``
        Output format: ``pretty`` (default), ``json``, or ``toon``.
        The ``toon`` format requires ``helgesverre/toon``.

    **Examples:**

    .. code-block:: terminal

        # List the collectors a profile actually has
        $ vendor/bin/mate resources:read symfony-profiler://profile/abc123

        # Read one collector
        $ vendor/bin/mate resources:read symfony-profiler://profile/abc123/db

        # JSON output for scripting
        $ vendor/bin/mate resources:read symfony-profiler://profile/abc123 --format=json


Security
--------

Discovered extensions are written to ``mate/extensions.php`` and new entries default to
``enabled: true``. Disable specific packages by setting their ``enabled`` flag to ``false``.

Packages that set ``extra.ai-mate.extension`` to ``false`` are excluded from discovery entirely.

Further Reading
---------------

.. toctree::
    :maxdepth: 1

    mate/integration
    mate/creating-extensions
    mate/troubleshooting

.. _`multi-kernel applications`: https://symfony.com/doc/current/configuration/multiple_kernels.html
