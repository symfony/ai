Integration
===========

This page explains how to integrate Symfony AI Mate with AI development tools.

JetBrains AI Assistant
----------------------

To connect Symfony AI Mate to JetBrains AI Assistant (see `JetBrains MCP documentation`_ for more details):

1. Press ``Cmd`` + ``,`` (macOS) or ``Ctrl`` + ``Alt`` + ``S`` (Windows/Linux) to open **Settings**.
2. Navigate to **Tools | AI Assistant | Model Context Protocol (MCP)**.
3. Click the **+** (Add) button.
4. Configure the server parameters:

   - **Name**: Symfony AI Mate
   - **Command type**: Select ``stdio``
   - **Executable**: ``php``
   - **Arguments**: ``/absolute/path/to/vendor/bin/mate serve``

5. Click **OK** to save.

.. note::

    Replace ``/absolute/path/to/`` with the actual path to your project's vendor directory.

Claude Desktop
--------------

To connect Symfony AI Mate to Claude Desktop (see `Claude Desktop MCP documentation`_ for more details):

1. Open Claude Desktop.
2. Go to **Settings** > **Developer** and click **Edit Config**.

   Alternatively, open the file manually:

   - **macOS**: ``~/Library/Application Support/Claude/claude_desktop_config.json``
   - **Windows**: ``%APPDATA%\Claude\claude_desktop_config.json``

3. Add the server configuration to the ``mcpServers`` object:

   .. code-block:: json

       {
           "mcpServers": {
               "symfony-ai-mate": {
                   "command": "php",
                   "args": ["/absolute/path/to/vendor/bin/mate", "serve"]
               }
           }
       }

4. Save the file and restart Claude Desktop.

.. note::

    Replace ``/absolute/path/to/`` with the actual path to your project's vendor directory.

Claude Code
-----------

To add Symfony AI Mate to Claude Code (see `Claude Code MCP documentation`_ for more details):

.. code-block:: terminal

    $ claude mcp add mate $(pwd)/vendor/bin/mate serve --scope local
    $ claude mcp list  # Verify: mate - ✓ Connected

Troubleshooting
---------------

Claude Desktop Not Connecting
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Verify config file location**:

   - macOS: ``~/Library/Application Support/Claude/claude_desktop_config.json``
   - Windows: ``%APPDATA%\Claude\claude_desktop_config.json``

2. **Check JSON syntax**:

   .. code-block:: json

       {
           "mcpServers": {
               "symfony-ai-mate": {
                   "command": "php",
                   "args": ["/absolute/path/to/vendor/bin/mate", "serve"]
               }
           }
       }

3. **Use absolute paths** - relative paths often fail.

4. **Restart Claude Desktop** after configuration changes.

JetBrains AI Assistant Not Connecting
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Verify settings path**: Tools → AI Assistant → Model Context Protocol (MCP)

2. **Check configuration**:

   - Command type: ``stdio``
   - Executable: ``php``
   - Arguments: ``/absolute/path/to/vendor/bin/mate serve``

3. **Test manually** from the same directory as your IDE.

Claude Code Not Connecting
~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Check connection status**:

   .. code-block:: terminal

       $ claude mcp list

   Look for ``mate - ✓ Connected``

2. **Re-add the server**:

   .. code-block:: terminal

       $ claude mcp remove mate
       $ claude mcp add mate $(pwd)/vendor/bin/mate serve --scope local

3. **Check for conflicting servers** with similar names.

For general server issues and debugging tips, see the :doc:`troubleshooting` guide.

.. _`JetBrains MCP documentation`: https://www.jetbrains.com/help/idea/model-context-protocol.html
.. _`Claude Desktop MCP documentation`: https://docs.anthropic.com/en/docs/build-with-claude/mcp
.. _`Claude Code MCP documentation`: https://docs.anthropic.com/en/docs/build-with-claude/claude-code
