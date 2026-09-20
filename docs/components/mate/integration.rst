Integration
===========

Symfony AI Mate (``vendor/bin/mate``) is a CLI that gives coding agents project-aware tools for a
PHP application: the compiled container, the profiler and the logs. This page explains how your
coding agent finds Mate, and how to make sure it runs Mate under the right PHP.

There is no server to configure. Mate is a CLI, so any agent that can run a shell command can
already use it. The integration problem is a different one: **an agent will not use a tool it does
not know exists.**

How Agents Find Mate
--------------------

``mate init`` and ``mate discover`` write three files:

``mate/AGENT_INSTRUCTIONS.md``
    The aggregated instructions of every enabled extension: which tools exist and when to reach for
    them. The whole file is rewritten on every ``discover``, so do not edit it. Put your own notes
    into ``AGENTS.md`` outside the markers described below.

A managed block in ``AGENTS.md``
    A summary that points at the CLI, delimited by ``<!-- BEGIN AI_MATE_INSTRUCTIONS -->`` and
    ``<!-- END AI_MATE_INSTRUCTIONS -->``. Mate rewrites only what is between those markers.
    Anything else in your ``AGENTS.md`` is preserved.

A managed block in ``CLAUDE.md``
    An ``@AGENTS.md`` import, delimited by ``<!-- BEGIN AI_MATE_AGENTS_IMPORT -->`` and
    ``<!-- END AI_MATE_AGENTS_IMPORT -->``. Claude Code reads ``CLAUDE.md`` and would otherwise
    never see ``AGENTS.md``.

On top of that, ``mate discover`` installs the Agent Skills of every enabled extension into
``.agents/skills/`` and mirrors them into ``.claude/skills/``. See :doc:`skills`.

Run ``vendor/bin/mate discover`` whenever you add or remove an extension. With the Composer plugin
this happens automatically after ``composer install`` and ``composer update``.

Per-Agent Notes
---------------

Claude Code
~~~~~~~~~~~

Works out of the box after ``mate init``. It reads ``CLAUDE.md``, which imports ``AGENTS.md``, and
loads skills from ``.claude/skills/``. To verify, run this from a Claude Code session:

.. code-block:: terminal

    $ vendor/bin/mate tools:list

If it prefers its own approach, see :ref:`mate-agent-ignores-mate`.

Codex
~~~~~

Reads ``AGENTS.md`` and ``.agents/skills/`` directly. No wrapper and no configuration are needed.

.. note::

    Mate versions before 0.13 shipped ``bin/codex`` wrappers to inject an MCP configuration. Mate
    no longer runs an MCP server, so the wrappers are gone.

GitHub Copilot, Cursor, OpenCode
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

These read ``AGENTS.md`` and, where supported, ``.agents/skills/``. If your agent uses a different
instruction file, import ``AGENTS.md`` from it the way ``CLAUDE.md`` does, or point the agent at
``mate/AGENT_INSTRUCTIONS.md``.

JetBrains AI Assistant
~~~~~~~~~~~~~~~~~~~~~~

Add the contents of ``mate/AGENT_INSTRUCTIONS.md`` to the project instructions, and allow the
assistant to run ``vendor/bin/mate``.

.. _mate-choosing-the-interpreter:

Choosing the PHP Interpreter
----------------------------

Mate runs under whichever ``php`` the agent's shell resolves. That is correct on a machine with a
single PHP installation and no containers. It is wrong in exactly the setups where Mate is most
useful:

* **ddev, Docker, Lando**: the application, its database and its profiler cache live inside the
  container. A ``mate`` started on the host may not reach them, or reads a different filesystem.
* **Several PHP versions side by side** (brew, phpenv, distro packages): the shell default is not
  necessarily the one the project targets.
* **PHP extensions**: a tool that needs an extension the default binary lacks fails in a way that
  looks like a bug in Mate.

This matters more than for an ordinary console command. Mate reads the compiled container, the
profiler cache and the logs of *this* project. Under the wrong interpreter it either fails, or it
reports on something that is not the application under test.

``mate init`` asks which command your coding agent should use and writes two parameters::

    // mate/config.php
    $container->parameters()
        ->set('mate.invocation', 'ddev exec vendor/bin/mate')
        ->set('mate.php_version', '8.3')
    ;

``mate.invocation``
    The full command the agent must use, wrapper included. It is written into
    ``mate/AGENT_INSTRUCTIONS.md`` and the managed ``AGENTS.md`` block, so the prefix ends up where
    the agent reads it.

    When a ``.ddev/`` directory is present, ``mate init`` proposes ``ddev exec vendor/bin/mate``.
    Answering with a wrapper alone is enough: ``symfony php`` is recorded as
    ``symfony php vendor/bin/mate``. The default is the plain ``vendor/bin/mate``.

``mate.php_version``
    The PHP version the project runs on, as ``major.minor``. When ``mate.invocation`` wraps the
    binary, ``mate init`` runs ``php`` through that wrapper to find out which interpreter it
    reaches. If the wrapper cannot be reached, ``init`` warns and records the version of the
    current process. Check the value by hand in that case.

    Mate refuses to start under a different version and names ``mate.invocation`` in the error:

    .. code-block:: terminal

        $ vendor/bin/mate tools:list

         [ERROR] Mate is running under PHP "8.4.15" but this project expects PHP "8.3".
                 Run it as "ddev exec vendor/bin/mate".

    Set the parameter to ``null`` to disable the check.

    ``init``, ``discover``, ``list``, ``help`` and ``completion`` only print a warning and still
    run. ``init`` writes this configuration in the first place. ``discover`` reads Composer
    metadata and runs unattended after every ``composer install``. The others never read the
    application. The warning makes a wrong interpreter visible before it reaches a command that
    does refuse.

After changing either parameter, run ``vendor/bin/mate discover`` so the instructions pick the new
command up.

.. caution::

    With ``--no-interaction``, ``mate init`` does not ask. It records ``vendor/bin/mate`` and the
    PHP version of the process that runs ``init``. Only a ``.ddev/`` directory changes that default.
    Running ``init`` again does not correct it, because the question is skipped once
    ``mate/config.php`` exists. For a project in Docker Compose, Lando or a similar setup, edit
    both parameters by hand and run ``vendor/bin/mate discover``.
