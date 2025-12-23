Troubleshooting
===============

This page covers common issues when using Symfony AI Mate and how to resolve them.

For specific issues, see also:

* :doc:`integration` - AI assistant connection issues
* :doc:`creating-extensions` - Extension and tool issues

Server Issues
-------------

Server Not Starting
~~~~~~~~~~~~~~~~~~~

If the MCP server doesn't start:

1. **Check PHP version** (requires 8.2+):

   .. code-block:: terminal

       $ php --version

2. **Verify the binary exists**:

   .. code-block:: terminal

       $ ls -la vendor/bin/mate

3. **Run manually to see errors**:

   .. code-block:: terminal

       $ vendor/bin/mate serve

   Look for error messages in the output.

4. **Check for missing dependencies**:

   .. code-block:: terminal

       $ composer install

Server Crashes on Startup
~~~~~~~~~~~~~~~~~~~~~~~~~

If the server starts but immediately crashes:

1. **Check for syntax errors** in your custom tools:

   .. code-block:: terminal

       $ php -l mate/MyTool.php

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

    > php vendor/bin/mate serve

Debugging Tips
--------------

Enable Debug Logging
~~~~~~~~~~~~~~~~~~~~

Set the ``MATE_DEBUG`` environment variable to enable debug-level logging:

.. code-block:: terminal

    $ MATE_DEBUG=1 vendor/bin/mate serve

This outputs detailed debug information to stderr, including:

- Service registration details
- Extension discovery information
- Tool execution logs
- Internal state changes

Log to File
~~~~~~~~~~~

Set the ``MATE_DEBUG_FILE`` environment variable to redirect logs to a file:

.. code-block:: terminal

    $ MATE_DEBUG_FILE=1 vendor/bin/mate serve

This creates a ``dev.log`` file in the current directory with all log output.
This is particularly useful when running the server through AI assistants (like Claude Code)
where stderr output may not be easily accessible.

To customize the log file path, use the ``MATE_DEBUG_LOG_FILE`` environment variable:

.. code-block:: terminal

    $ MATE_DEBUG_FILE=1 MATE_DEBUG_LOG_FILE=/var/log/mate/debug.log vendor/bin/mate serve

You can combine both environment variables for debug logging to file:

.. code-block:: terminal

    $ MATE_DEBUG=1 MATE_DEBUG_FILE=1 vendor/bin/mate serve

For AI assistant integration (e.g., Claude Code MCP configuration), add these to the server configuration:

.. code-block:: json

    {
        "mcpServers": {
            "symfony-ai-mate": {
                "command": "php",
                "args": ["vendor/bin/mate", "serve"],
                "env": {
                    "MATE_DEBUG": "1",
                    "MATE_DEBUG_FILE": "1"
                }
            }
        }
    }

Test Tools Manually
~~~~~~~~~~~~~~~~~~~

Create a simple test script::

    // test-tool.php
    require 'vendor/autoload.php';

    $tool = new App\Mate\MyTool();
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
