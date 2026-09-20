Symfony AI - Mate Component
===========================

The Mate component provides a command-line assistant (``vendor/bin/mate``) that gives your coding
agent (Claude Code, Codex, GitHub Copilot, Cursor, JetBrains AI, etc.) project-aware tools for your
PHP application: the compiled container, the profiler, the logs, and whatever you add yourself.

Mate is a plain CLI. The agent runs ``vendor/bin/mate tools:call …`` the way it runs any other
command. There is no server to start, no client-specific configuration file, and no permanent tool
descriptions that occupy the agent's context window. Any agent that can run a shell command can
use Mate.

Mate reads your application without booting it. The compiled container is parsed from the dumped
XML, and the profiler and logs are read from disk. Mate therefore still answers when the
application itself does not boot, which is usually the moment you need it.

The core package is framework-agnostic and works with any PHP application. More tools come from
:doc:`extensions <mate/extensions>`, which are Composer packages. Two of them are maintained in the
Symfony AI repository, one for Symfony and one for Monolog.

.. caution::

    Mate is a development tool. Install it with ``--dev`` and do not deploy it to production.

Installation
------------

.. code-block:: terminal

    $ composer require --dev symfony/ai-mate

For a Symfony application, add the two extensions:

.. code-block:: terminal

    $ composer require --dev symfony/ai-symfony-mate-extension symfony/ai-monolog-mate-extension

Quick Start
-----------

Initialize Mate in your project:

.. code-block:: terminal

    $ vendor/bin/mate init

``mate init`` asks which command your coding agent should use to run Mate. If your PHP runs in a
container or behind a wrapper, answer with the wrapper, for example ``ddev exec`` or
``symfony php``. Otherwise accept the default. :ref:`mate-choosing-the-interpreter` has the
details. The command then creates:

* ``mate/config.php`` for parameters and services
* ``mate/extensions.php``, the list of extensions that ``mate discover`` fills
* ``mate/.env`` and ``mate/.gitignore``
* ``mate/src/`` for your own tools
* ``mate/AGENT_INSTRUCTIONS.md``, a managed block in ``AGENTS.md``, and a ``CLAUDE.md`` that
  imports ``AGENTS.md`` so Claude Code picks the instructions up

It also adds this to your ``composer.json``:

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

``extension: false`` keeps your application from being discovered as a Mate extension when it is
installed as a dependency elsewhere.

Then register the new namespace and let Mate find the installed extensions:

.. code-block:: terminal

    $ composer dump-autoload
    $ vendor/bin/mate discover

``mate discover`` registers the installed extensions, writes the agent instructions and installs
the :doc:`skills <mate/skills>`. Check the result:

.. code-block:: terminal

    $ vendor/bin/mate tools:list

Then check that your coding agent can run the same command. If it does not pick Mate up on its own
afterwards, see :doc:`mate/integration`.

Keeping Mate Up to Date
~~~~~~~~~~~~~~~~~~~~~~~

``symfony/ai-mate`` installs the Composer plugin ``symfony/ai-mate-composer-plugin``. Once
``mate/extensions.php`` exists, Composer runs ``vendor/bin/mate discover --composer`` after every
``composer install`` and ``composer update``. New extensions, their instructions and their skills
arrive without a manual step.

Before ``mate init`` the plugin changes nothing. It only prints a hint to run ``init``.

Run ``vendor/bin/mate discover`` by hand after you changed the Mate configuration or worked on a
local extension.

Using Mate from a Coding Agent
------------------------------

There is nothing to start. The agent learns about Mate from ``AGENTS.md`` and the installed skills,
and works with four commands:

.. code-block:: terminal

    $ vendor/bin/mate tools:list                          # what is available
    $ vendor/bin/mate tools:inspect symfony-profiler-list # parameters and JSON input schema
    $ vendor/bin/mate tools:call symfony-profiler-list --limit=1
    $ vendor/bin/mate resources:read symfony-profiler://profile/<token>

Tool parameters are passed as long options, one per parameter, and cast to the declared type. A
boolean may be passed as a bare flag, and a variadic parameter takes the option repeated. Nested
values are passed as a JSON object, and so is a parameter whose name collides with a console option
like ``format``:

.. code-block:: terminal

    $ vendor/bin/mate tools:call monolog-search --term="^GET" --regex
    $ vendor/bin/mate tools:call <tool-name> --tag=a --tag=b
    $ vendor/bin/mate tools:call <tool-name> --json='{"filters": {"level": "error"}}'

All four commands accept ``--format``. Use ``--format=json`` when the result is parsed, and
``--format=toon`` (requires ``helgesverre/toon``) for the smallest context footprint. See
:doc:`mate/commands` for every command and option.

Adding Custom Tools
-------------------

Add a class with a public method that carries the ``#[MateTool]`` attribute to ``mate/src/``::

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
the ``@param`` PHPDoc. The description you write there is what the agent sees in
``tools:inspect``. Constructor dependencies are injected from Mate's DI container.

Two more attributes cover data the agent navigates into:

* ``#[MateResource]`` marks a method as a static resource with a fixed ``uri``.
* ``#[MateResourceTemplate]`` marks a method as a templated resource. The variables in its
  ``uriTemplate`` are passed to the method.

::

    // mate/src/MyResources.php
    namespace Mate;

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

A tool is something the agent *calls* with arguments. A resource is something it *addresses* and
can drill into, which keeps large payloads out of the context window until they are needed:

.. code-block:: terminal

    $ vendor/bin/mate tools:call my-tool --param=value
    $ vendor/bin/mate resources:read my-app://entity/42

Run ``composer dump-autoload`` when the autoloader does not know the new class yet, and verify with
``vendor/bin/mate tools:list``. To share tools between projects, see
:doc:`mate/creating-extensions`.

Configuration
-------------

Mate works without further configuration. Come back to this section when a default does not fit.

Parameters and Services
~~~~~~~~~~~~~~~~~~~~~~~

``mate/config.php`` is a Symfony DI configuration file. Set parameters of Mate and its extensions
there, and register your own services::

    // mate/config.php
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    return static function (ContainerConfigurator $container): void {
        $container->parameters()
            ->set('mate.invocation', 'vendor/bin/mate')
            ->set('mate.php_version', '8.3')
            ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/log')
        ;

        $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()

            // Register your custom services here
        ;
    };

The core parameters:

``mate.invocation``
    The command your coding agent uses to run Mate. Written by ``mate init``. See
    :ref:`mate-choosing-the-interpreter`.

``mate.php_version``
    The PHP version Mate must run under, or ``null`` to disable the check. Written by
    ``mate init``.

``mate.env_file``
    An env file to load, relative to the project root. Default ``null``.

``mate.cache_dir``
    Mate's cache directory. Defaults to a directory below ``sys_get_temp_dir()``.

``mate.root_dir``
    The project root. Read-only, use it to build paths.

The parameters of the extensions are listed in :doc:`mate/extensions`. For an application with
several kernels, see :ref:`mate-multi-kernel`.

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

Reference environment variables with the ``%env(VAR_NAME)%`` syntax, as in any
`Symfony configuration`_.

No env file is loaded by default. Set ``mate.env_file`` to a path relative to the project root to
load one. A ``.local`` file next to it is loaded as well::

    $container->parameters()
        ->set('mate.env_file', '.env')         // the env files of your application
        // ->set('mate.env_file', 'mate/.env') // or variables that only Mate needs
    ;

``mate init`` creates an empty ``mate/.env`` for the second case, and a ``mate/.gitignore`` that
keeps ``mate/.env.local`` out of the repository.

Enabling and Disabling Extensions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``mate/extensions.php`` records which extensions are enabled. ``mate discover`` maintains it: new
extensions are added as enabled, and the state of known extensions is kept. To disable an
extension, set its flag to ``false``::

    // mate/extensions.php
    return [
        'vendor/package-name' => ['enabled' => true],
        'vendor/another-package' => ['enabled' => false],
    ];

The file also holds the state of every skill. Only the ``enabled`` and ``mode`` keys are yours to
edit. Mate rewrites every other key on the next install. See :doc:`mate/skills`.

Disabling Single Tools
~~~~~~~~~~~~~~~~~~~~~~

To keep an extension but drop some of its tools or resources, use ``MateHelper`` in
``mate/config.php``::

    use Symfony\AI\Mate\Container\MateHelper;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    return static function (ContainerConfigurator $container): void {
        MateHelper::disableFeatures($container, [
            'symfony/ai-mate' => ['server-info'],
            'symfony/ai-monolog-mate-extension' => ['monolog-list-channels'],
        ]);
    };

Call ``disableFeatures()`` only once. A second call replaces the first one.

Extensions
----------

The core package ships one tool, ``server-info``. It reports the PHP version, the OS and the loaded
PHP extensions of the runtime Mate uses. Everything else comes from extensions:

* The **Symfony extension** (``symfony/ai-symfony-mate-extension``) searches the compiled container
  and reads the profiler.
* The **Monolog extension** (``symfony/ai-monolog-mate-extension``) searches the log files.

Both are documented in :doc:`mate/extensions`. Community extensions, for example for PHPUnit,
PHPStan or Rector, are listed in `awesome-mate`_ and installed the same way:

.. code-block:: terminal

    $ composer require --dev matesofmate/phpunit-extension

The Composer plugin then runs ``mate discover``, which enables the extension and installs its
skills. To write your own extension, see :doc:`mate/creating-extensions`.

Security
--------

Mate runs locally, with the permissions of the user who starts it. Two things deserve attention:

**Extensions are code.** Every installed package with an ``extra.ai-mate`` section is discovered
and enabled by default. Its tools run on your machine, and its instructions and skills are read by
your agent. Review ``mate/extensions.php`` after installing packages, and commit it together with
the generated skill folders so that changes show up in code review.

**Tool output is data.** Logs, profiles and container metadata contain text that end users and
third-party packages control. Both extensions mark such output as untrusted and redact known
sensitive values. See :ref:`mate-untrusted-data`. Redaction is best effort, so treat the output of
the profiler and log tools as sensitive.

Further Reading
---------------

.. toctree::
    :maxdepth: 1

    mate/integration
    mate/extensions
    mate/skills
    mate/commands
    mate/creating-extensions
    mate/troubleshooting

.. _`Symfony configuration`: https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables
.. _`awesome-mate`: https://github.com/MatesOfMate/awesome-mate
