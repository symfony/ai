Troubleshooting
===============

This page covers common issues when using Symfony AI Mate and how to resolve them.

For specific issues, see also:

* :doc:`integration` - the agent not finding or not using Mate
* :doc:`creating-extensions` - extension and tool issues

Command Issues
--------------

The Command Does Not Run
~~~~~~~~~~~~~~~~~~~~~~~~

1. **Check PHP version** (requires 8.2+):

   .. code-block:: terminal

       $ php --version

2. **Verify the binary exists**:

   .. code-block:: terminal

       $ ls -la vendor/bin/mate

3. **Check for missing dependencies**:

   .. code-block:: terminal

       $ composer install

4. **Run it directly to see the error**:

   .. code-block:: terminal

       $ vendor/bin/mate tools:list

Container Fails to Build
~~~~~~~~~~~~~~~~~~~~~~~~

Every invocation builds Mate's own DI container, which loads your ``mate/config.php``. If a command
fails before producing output:

1. **Check for syntax errors** in your custom tools:

   .. code-block:: terminal

       $ php -l mate/src/MyTool.php

2. **Verify service configuration**:

   .. code-block:: terminal

       $ php -r "require 'vendor/autoload.php'; include 'mate/config.php';"

3. **Check for circular dependencies** in your service configuration.

Permission Denied Errors
~~~~~~~~~~~~~~~~~~~~~~~~

If you get permission errors:

.. code-block:: terminal

    $ chmod +x vendor/bin/mate

On Windows, ensure PHP is in your PATH and run:

.. code-block:: terminal

    > php vendor/bin/mate tools:list

Discovery Issues
----------------

A Tool Does Not Appear
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate tools:list
    $ vendor/bin/mate debug:capabilities

1. **Run** ``composer dump-autoload``. Mate resolves the class name from the file and skips any file
   whose class cannot be autoloaded. This is the most common cause for a tool under ``mate/src/``
   that never shows up.

2. **Check the scan directories**. For your own tools, ``extra.ai-mate.scan-dirs`` in
   ``composer.json`` must cover the directory the class lives in.

3. **Check the method is public** and carries ``#[MateTool]``. Abstract classes, interfaces, traits
   and enums are skipped.

4. **Check the feature is not disabled** in ``mate/config.php`` via
   ``MateHelper::disableFeatures()``.

5. **Enable debug logging** (see below). Mate logs the classes it could not autoload and the files
   it failed to process.

An Extension Does Not Load
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate debug:extensions --show-all

``[not loaded]`` means the package was configured but could not be loaded; ``[enabled]`` without
``[loaded]`` usually means the package was removed without updating ``mate/extensions.php``. Run
``vendor/bin/mate discover`` to resynchronize.

Instructions or Skills Are Stale
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate discover        # refresh extensions, instructions and skills
    $ vendor/bin/mate skills:validate # check the generated folders against the recorded state

``skills:validate`` reports hand-edited content, missing folders and sources that moved on since the
last install. If you want to own a skill's content, set its ``mode`` to ``override`` in
``mate/extensions.php`` rather than editing the generated folder.

Tool Execution Issues
---------------------

A Parameter Is Not Accepted
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Check the actual schema before guessing:

.. code-block:: terminal

    $ vendor/bin/mate tools:inspect <tool-name>

Values are coerced to the parameter's declared type, so a value that cannot be cast is rejected
rather than silently passed through, naming the option and the value it rejected.

A variadic parameter takes the option repeated:

.. code-block:: terminal

    $ vendor/bin/mate tools:call some-tool --tag=a --tag=b

Repeating an option that is not variadic is an error, so a typo cannot quietly discard the first
value. Nested or associative values have no option form; pass them as JSON:

.. code-block:: terminal

    $ vendor/bin/mate tools:call some-tool --json='{"filters": {"level": "error"}}'

The same applies to a parameter whose name is taken by a console option (``format``, ``json``,
``help``, ``silent``, ``quiet``, ``verbose``, ``version``, ``ansi``, ``no-ansi``,
``no-interaction``).

The Output Is Too Large
~~~~~~~~~~~~~~~~~~~~~~~

Prefer a resource URI over dumping everything, and use a compact format:

.. code-block:: terminal

    $ vendor/bin/mate tools:call symfony-profiler-list --limit=1 --format=json
    $ vendor/bin/mate resources:read symfony-profiler://profile/<token> --format=toon

``--format=toon`` requires ``helgesverre/toon`` and produces the smallest context footprint.

Debugging Tips
--------------

Enable Debug Logging
~~~~~~~~~~~~~~~~~~~~

Set the ``MATE_DEBUG`` environment variable to enable debug-level logging:

.. code-block:: terminal

    $ MATE_DEBUG=1 vendor/bin/mate tools:list

This outputs detailed debug information to stderr, including:

- Service registration details
- Extension discovery information
- Classes that could not be autoloaded during discovery
- Tool execution logs

Log to File
~~~~~~~~~~~

Set the ``MATE_DEBUG_FILE`` environment variable to redirect logs to a file:

.. code-block:: terminal

    $ MATE_DEBUG_FILE=1 vendor/bin/mate tools:list

This creates a ``dev.log`` file in the current directory with all log output. This is particularly
useful when the command is run by a coding agent, where stderr may not be easily accessible.

To customize the log file path, use the ``MATE_DEBUG_LOG_FILE`` environment variable:

.. code-block:: terminal

    $ MATE_DEBUG_FILE=1 MATE_DEBUG_LOG_FILE=/var/log/mate/debug.log vendor/bin/mate tools:list

You can combine both environment variables:

.. code-block:: terminal

    $ MATE_DEBUG=1 MATE_DEBUG_FILE=1 vendor/bin/mate tools:list

Test Tools Manually
~~~~~~~~~~~~~~~~~~~

Call the tool through the CLI, which exercises the same discovery, schema and casting path the agent
uses:

.. code-block:: terminal

    $ vendor/bin/mate tools:call my-tool --param=test-value

To bypass Mate entirely and test the method in isolation::

    // test-tool.php
    require 'vendor/autoload.php';

    $tool = new Mate\MyTool();
    var_dump($tool->execute('test-param'));

Clear Cache
~~~~~~~~~~~

If you're experiencing stale behavior:

.. code-block:: terminal

    $ vendor/bin/mate clear-cache

Getting Help
------------

If you're still experiencing issues:

1. **Check the documentation**: Review the :doc:`../mate` main documentation
2. **Search existing issues**: https://github.com/symfony/ai/issues
3. **Create a new issue**: Include:

   - PHP version (``php --version``)
   - Symfony AI Mate version
   - Error messages or logs
   - Steps to reproduce
   - Your configuration files (sanitized)
