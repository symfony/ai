Integration
===========

This page explains how to make Symfony AI Mate discoverable to your coding agent.

There is no server to configure. Mate is a CLI, so any agent that can run a shell command can
already use it. The whole integration problem is a different one: **an agent will not use a tool it
does not know exists.** Everything below is about closing that gap.

How agents find Mate
--------------------

``mate init`` and ``mate discover`` write three things:

``mate/AGENT_INSTRUCTIONS.md``
    The aggregated instructions of every enabled extension: which tools exist and when to reach for
    them.

A managed block in ``AGENTS.md``
    A summary pointing at the CLI, delimited by ``<!-- BEGIN AI_MATE_INSTRUCTIONS -->`` and
    ``<!-- END AI_MATE_INSTRUCTIONS -->``. Mate rewrites only what is between those markers, so
    anything else in your ``AGENTS.md`` is preserved.

``CLAUDE.md``
    A managed ``@AGENTS.md`` import, because Claude Code reads ``CLAUDE.md`` and would otherwise
    never see ``AGENTS.md``.

On top of that, ``mate discover`` installs the Agent Skills of every enabled extension into
``.agents/skills/`` and mirrors them into ``.claude/skills/``. See the Skills section of the
:doc:`component documentation <../mate>` for the details.

Re-run ``vendor/bin/mate discover`` whenever you add or remove an extension. With the Composer
plugin installed this happens automatically after ``composer install`` and ``composer update``.

Per-agent notes
---------------

Claude Code
~~~~~~~~~~~

Works out of the box after ``mate init``: it reads ``CLAUDE.md``, which imports ``AGENTS.md``, and
loads skills from ``.claude/skills/``. Verify with:

.. code-block:: terminal

    $ vendor/bin/mate tools:list

and ask Claude Code to run it. If it prefers its own approach, see `The agent ignores Mate`_.

Codex
~~~~~

Reads ``AGENTS.md`` and ``.agents/skills/`` directly. No wrapper and no configuration are needed
any more: earlier versions of Mate shipped ``bin/codex`` wrappers because Codex does not read a
project-local MCP configuration, but without an MCP server there is nothing left to inject.

GitHub Copilot, Cursor, OpenCode
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

These read ``AGENTS.md`` and, where supported, ``.agents/skills/``. If your agent uses a different
instruction file, import ``AGENTS.md`` from it the way ``CLAUDE.md`` does, or point the agent at
``mate/AGENT_INSTRUCTIONS.md``.

JetBrains AI Assistant
~~~~~~~~~~~~~~~~~~~~~~

Add the contents of ``mate/AGENT_INSTRUCTIONS.md`` to the project instructions, and allow the
assistant to run ``vendor/bin/mate``.

Choosing the PHP interpreter
----------------------------

Mate runs under whichever ``php`` the agent's shell resolves. That is correct on a machine with a
single PHP installation and no containers, and wrong in exactly the setups where Mate is most
useful:

* **ddev, Docker, Lando** - the application, its database and its profiler cache live inside the
  container. A ``mate`` started on the host may not reach them, or reads a different filesystem
  entirely.
* **Several PHP versions side by side** (brew, phpenv, distro packages) - the shell default is not
  necessarily the one the project targets.
* **Extensions** - a tool that needs an extension the default binary lacks fails in a way that looks
  like a bug in Mate.

This matters more than for an ordinary console command, because Mate reads the profiler cache and
the compiled container of *this* project. Run under the wrong interpreter, it either fails to start
or reports something that is not the application under test.

``mate init`` asks for this and records it, so in most cases you do not have to do anything: it
proposes ``ddev exec vendor/bin/mate`` when a ``.ddev/`` directory is present, writes the answer to
``mate.invocation`` in ``mate/config.php``, and materializes it into ``mate/AGENT_INSTRUCTIONS.md``
and the managed ``AGENTS.md`` block. It also records the PHP version it ran under as
``mate.php_version``, and Mate refuses to start under a different one rather than reporting on the
wrong runtime.

To change it later, edit the two parameters::

    // mate/config.php
    $container->parameters()
        ->set('mate.invocation', 'docker compose exec php vendor/bin/mate')
        ->set('mate.php_version', '8.3')
    ;

Then run ``vendor/bin/mate discover`` so the instructions pick the new command up.

Troubleshooting
---------------

.. _`The agent ignores Mate`:

The agent ignores Mate
~~~~~~~~~~~~~~~~~~~~~~

The most common failure, and it is a discovery problem rather than a technical one.

1. **Confirm the instructions exist**:

   .. code-block:: terminal

       $ vendor/bin/mate discover

   Then check that ``AGENTS.md`` contains the managed block, and that ``CLAUDE.md`` imports it.

2. **Confirm the skills are installed**:

   .. code-block:: terminal

       $ vendor/bin/mate skills:list

   A skill in state ``disabled`` is not installed; an empty list means no enabled extension ships
   skills.

3. **Confirm the agent reads the file it needs.** Claude Code needs ``CLAUDE.md``, Codex needs
   ``AGENTS.md``. If you keep your own instruction file, it has to import one of them.

4. **Say it explicitly once.** Asking the agent to run ``vendor/bin/mate tools:list`` is enough to
   establish that the tools exist; the instructions carry it from there.

The command works, the tools are empty
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate debug:extensions
    $ vendor/bin/mate debug:capabilities

If an extension shows as ``[not loaded]``, the package is missing or failed to load. If your own
tools under ``mate/src/`` are absent, run ``composer dump-autoload``: Mate resolves the class name
from the file and skips files whose class cannot be autoloaded.

Wrong PHP or wrong environment
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ vendor/bin/mate tools:call server-info

This reports the PHP version, OS and loaded extensions of the runtime Mate is using. If that is not
the runtime serving your application, see `Choosing the PHP interpreter`_.

If Mate refuses to start with a PHP version mismatch instead, that is the same problem caught
earlier: run the command it names, or correct ``mate.php_version`` if the recorded value is the
wrong one.

For general debugging tips, see the :doc:`troubleshooting` guide.
