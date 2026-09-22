Troubleshooting
===============

Symfony AI Mate (``vendor/bin/mate``) is a CLI that gives coding agents project-aware tools for a
PHP application: the compiled container, the profiler and the logs.

Start with these three commands. They answer most questions below:

.. code-block:: terminal

    $ vendor/bin/mate debug:extensions --show-all  # what was discovered, enabled and loaded
    $ vendor/bin/mate debug:capabilities           # which tools and resources exist
    $ vendor/bin/mate tools:call server-info       # which PHP runtime Mate uses

The Command Does Not Run
------------------------

Nothing Happens or the Binary Is Missing
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Check the PHP version with ``php --version``. Mate requires PHP 8.2 or higher.
2. Check that ``vendor/bin/mate`` exists. If not, run ``composer install``.
3. On a permission error, run ``chmod +x vendor/bin/mate``. On Windows, run
   ``php vendor/bin/mate tools:list``.

PHP Version Mismatch
~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

     [ERROR] Mate is running under PHP "8.4.15" but this project expects PHP "8.3".
             Run it as "ddev exec vendor/bin/mate".

Run the command the error names. If the recorded version is the wrong one, correct
``mate.php_version`` in ``mate/config.php``. See :ref:`mate-choosing-the-interpreter`.

If Mate starts but reports on the wrong environment, ``server-info`` shows the PHP version, OS and
loaded extensions of the runtime Mate uses. If that is not the runtime serving your application,
change ``mate.invocation``.

The Container Fails to Build
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Every invocation builds Mate's own DI container, which loads your ``mate/config.php`` and the
service files of all enabled extensions. If a command fails before producing output:

1. Check your tools and configuration for syntax errors:

   .. code-block:: terminal

       $ php -l mate/src/MyTool.php
       $ php -l mate/config.php

2. Look for a circular dependency or a missing argument in your service configuration.
3. Disable the extension you added last in ``mate/extensions.php`` and try again.

.. _mate-agent-ignores-mate:

The Agent Ignores Mate
----------------------

The most common failure. It is a discovery problem, not a technical one.

1. **Confirm the instructions exist.** Run ``vendor/bin/mate discover``. Then check that
   ``AGENTS.md`` contains the managed block and that ``CLAUDE.md`` imports it.

2. **Confirm the skills are installed.** Run ``vendor/bin/mate skills:list``. A skill in state
   ``disabled`` is not installed. An empty list means no enabled extension ships skills.

3. **Confirm the agent reads the file it needs.** Claude Code needs ``CLAUDE.md``, Codex needs
   ``AGENTS.md``. If you keep your own instruction file, it has to import one of them. See
   :doc:`integration`.

4. **Say it explicitly once.** Asking the agent to run ``vendor/bin/mate tools:list`` is enough to
   establish that the tools exist. The instructions carry it from there.

Extensions and Tools Are Missing
--------------------------------

An Extension Is Not Discovered
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. The package needs an ``extra.ai-mate`` section in its ``composer.json``.
2. A package that sets ``extra.ai-mate.extension`` to ``false`` is never discovered as an
   extension of another project. That is what ``mate init`` writes into an application. The flag
   does not affect the application's own tools under ``mate/src/``.
3. Run ``vendor/bin/mate discover`` and check that the package is listed in
   ``mate/extensions.php`` with ``'enabled' => true``.

An Extension Does Not Load
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate debug:extensions --show-all

``[not loaded]`` means the package is configured but could not be loaded. ``[enabled]`` without
``[loaded]`` usually means the package was removed while ``mate/extensions.php`` still lists it.
Run ``vendor/bin/mate discover`` to resynchronize.

A Tool Does Not Appear
~~~~~~~~~~~~~~~~~~~~~~

1. **Run** ``composer dump-autoload``. Mate resolves the class name from the file and skips any
   file whose class cannot be autoloaded. This is the most common cause for a tool under
   ``mate/src/`` that never shows up.

2. **Check the scan directories.** ``extra.ai-mate.scan-dirs`` in ``composer.json`` must cover the
   directory the class lives in.

3. **Check the method.** It must be public and carry ``#[MateTool]``. Abstract classes,
   interfaces, traits and enums are skipped.

4. **Check the feature is not disabled** through ``MateHelper::disableFeatures()`` in
   ``mate/config.php``.

5. **Enable debug logging**, see `Debug Logging`_. Mate logs the classes it could not autoload and
   the files it failed to process.

A Dependency Is Not Injected
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Register the service in a file listed under ``extra.ai-mate.includes``::

       $services->set(MyService::class)
           ->autowire()
           ->autoconfigure();

2. Bind interfaces to an implementation::

       $services->alias(MyInterface::class, MyImplementation::class);

The Instructions of an Extension Are Missing
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Check that the file named by ``extra.ai-mate.instructions`` exists. The path is relative to the
package root. ``vendor/bin/mate debug:extensions`` prints an ``Agent instructions`` line for every
extension that has one. With ``--format=json`` the key is ``agent_instructions``.

Instructions or Skills Are Stale
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate discover        # refresh extensions, instructions and skills
    $ vendor/bin/mate skills:validate # check the generated folders against the recorded state

``skills:validate`` reports hand-edited content, missing folders and sources that changed since the
last install. To own the content of a skill, run ``vendor/bin/mate skills:override <name>``. Do not
edit the generated folder. See :doc:`skills`.

Tool Calls Fail
---------------

A Parameter Is Not Accepted
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Check the schema before guessing:

.. code-block:: terminal

    $ vendor/bin/mate tools:inspect <tool-name>

Values are cast to the declared type of the parameter. A value that cannot be cast is rejected,
and the error names the option and the value.

A variadic parameter takes the option repeated. Repeating an option that is not variadic is an
error, so a typo cannot quietly discard the first value:

.. code-block:: terminal

    $ vendor/bin/mate tools:call <tool-name> --tag=a --tag=b

Nested or associative values have no option form. Pass them as JSON:

.. code-block:: terminal

    $ vendor/bin/mate tools:call <tool-name> --json='{"filters": {"level": "error"}}'

The same applies to a parameter whose name is taken by a console option, like ``format`` or
``verbose``. See ``tools:call`` in the :doc:`commands` for the full list.

A Custom Tool Fails When Called
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A tool must return a scalar value or an array. An object fails when the result is encoded::

    // Good
    public function execute(): string { return 'result'; }
    public function execute(): array { return ['key' => 'value']; }

    // Bad
    public function execute(): object { return new \stdClass(); }

To rule out Mate, test the method in isolation::

    // test-tool.php
    require 'vendor/autoload.php';

    $tool = new Mate\MyTool();
    var_dump($tool->execute('test-param'));

The Output Is Too Large
~~~~~~~~~~~~~~~~~~~~~~~

Narrow the filter, read a resource URI over dumping everything, and use a compact format:

.. code-block:: terminal

    $ vendor/bin/mate tools:call symfony-profiler-list --limit=1 --format=json
    $ vendor/bin/mate resources:read symfony-profiler://profile/<token> --format=toon

See :ref:`mate-output-formats`.

Symfony and Monolog Extension Issues
------------------------------------

The Container Is Not Found
~~~~~~~~~~~~~~~~~~~~~~~~~~

``symfony-services`` and ``symfony-service-detail`` read the dumped container XML.

1. Warm the cache with ``php bin/console cache:warmup``. The XML is only dumped in debug mode.
2. Check that ``ai_mate_symfony.cache_dir`` points at the cache directory of the application.
3. Check that a ``*DebugContainer.xml`` file exists below it.

Profiles Are Not Found
~~~~~~~~~~~~~~~~~~~~~~

1. Check that ``ai_mate_symfony.profiler_dir`` points at the right directory.
2. Check that the profiler is enabled in the environment you look at.
3. Send a request to the application, so there is a profile to read.

If a single collector is missing, it was not enabled when the profile was captured.

Logs Are Not Found or Not Parsed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Check that ``ai_mate_monolog.log_dir`` points at the directory with your log files. Run
   ``vendor/bin/mate tools:call monolog-list-files`` to see what Mate finds.
2. The extension reads the standard Monolog line format and JSON. Entries in a custom format are
   skipped.
3. Check the file permissions of the log files.

Debugging Tips
--------------

Debug Logging
~~~~~~~~~~~~~

``MATE_DEBUG=1`` writes debug-level logs to stderr: service registration, extension discovery and
classes that could not be autoloaded.

.. code-block:: terminal

    $ MATE_DEBUG=1 vendor/bin/mate tools:list

``MATE_DEBUG_FILE=1`` writes the logs to ``dev.log`` in the project root, whatever the working
directory is. That helps when a coding agent runs the command and stderr is hard to reach.
``MATE_DEBUG_LOG_FILE`` changes the path. A relative path is resolved against the project root, an
absolute path is used as it is. The variables can be combined:

.. code-block:: terminal

    $ MATE_DEBUG=1 MATE_DEBUG_FILE=1 MATE_DEBUG_LOG_FILE=var/log/mate.log vendor/bin/mate tools:list
    $ MATE_DEBUG_FILE=1 MATE_DEBUG_LOG_FILE=/tmp/mate-debug.log vendor/bin/mate tools:list

Clear the Cache
~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate clear-cache

Getting Help
------------

Search the `existing issues`_ first. For a community extension, use the issue tracker of that
extension. `awesome-mate`_ links them. When you open a new issue, include:

* the PHP version and the Symfony AI Mate version
* the error message, or the log written with ``MATE_DEBUG=1``
* the steps to reproduce
* your ``mate/config.php`` and ``mate/extensions.php``, without secrets

.. _`existing issues`: https://github.com/symfony/ai/issues
.. _`awesome-mate`: https://github.com/MatesOfMate/awesome-mate
