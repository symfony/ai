Command Reference
=================

Symfony AI Mate (``vendor/bin/mate``) is a CLI that gives coding agents project-aware tools for a
PHP application: the compiled container, the profiler and the logs. This page lists the commands of
the CLI itself. The parameters of the individual tools are listed in :doc:`extensions`.

Every command is run through ``vendor/bin/mate``, or through the wrapper recorded as
``mate.invocation`` (see :ref:`mate-choosing-the-interpreter`). The standard Symfony Console
commands ``list``, ``help`` and ``completion`` are available as well.

.. _mate-output-formats:

Output Formats
--------------

Most commands accept ``--format``. The default is made for humans and differs per command
(``table``, ``text`` or ``pretty``). Two formats are made for machines:

``json``
    Use it whenever the result is parsed.

``toon``
    The smallest context footprint. Requires the ``helgesverre/toon`` package:

    .. code-block:: terminal

        $ composer require --dev helgesverre/toon

Setup
-----

``mate init``
    Create the ``mate/`` directory with its configuration files, register the ``Mate\`` namespace
    and the ``extra.ai-mate`` section in ``composer.json``, and write the agent instructions.
    It asks which command your coding agent should use to run Mate. See
    :ref:`mate-choosing-the-interpreter`.

``mate discover``
    Scan the installed Composer packages for Mate extensions. This command:

    * finds packages with an ``extra.ai-mate`` section in their ``composer.json``
    * adds new extensions to ``mate/extensions.php``, enabled by default
    * keeps the enabled or disabled state of extensions it already knows
    * installs the skills of every enabled extension (see :doc:`skills`)
    * rewrites ``mate/AGENT_INSTRUCTIONS.md``, the managed block in ``AGENTS.md`` and the
      ``@AGENTS.md`` import in ``CLAUDE.md``

    **Options:**

    ``--composer``
        Compact output. Used by the Composer plugin.

    ``--ignore-missing-file``
        Exit successfully without doing anything when ``mate/extensions.php`` does not exist.
        Made for Composer scripts that run before the project was initialized.

``mate clear-cache``
    Clear Mate's cache directory (``mate.cache_dir``).

Tools and Resources
-------------------

These are the four commands a coding agent uses.

``mate tools:list``
    List all tools with their description and arguments.

    **Options:**

    ``--filter=PATTERN``
        Filter by tool name. Supports wildcards like ``search*`` or ``*logs``.

    ``--extension=EXTENSION``
        Filter by extension package name. Use ``_custom`` for the tools of your own project.

    ``--format=FORMAT``
        ``table`` (default), ``json`` or ``toon``.

    **Examples:**

    .. code-block:: terminal

        $ vendor/bin/mate tools:list
        $ vendor/bin/mate tools:list --filter="monolog*"
        $ vendor/bin/mate tools:list --extension=symfony/ai-monolog-mate-extension --filter="*search"
        $ vendor/bin/mate tools:list --format=json

``mate tools:inspect <tool-name>``
    Show one tool with its full JSON input schema. Use it before guessing a parameter name.

    **Options:**

    ``--format=FORMAT``
        ``text`` (default), ``json`` or ``toon``.

    **Examples:**

    .. code-block:: terminal

        $ vendor/bin/mate tools:inspect monolog-search
        $ vendor/bin/mate tools:inspect server-info --format=json

``mate tools:call <tool-name>``
    Execute a tool. Each tool parameter is passed as a long option.

    **Options:**

    ``--<param>=<value>``
        One option per tool parameter. The value is cast to the declared type of the parameter.
        A value that cannot be cast is rejected with the name of the option. A boolean parameter
        may be passed as a bare ``--<flag>``. Repeat the option to pass several values to a
        variadic parameter. Repeating it for a single-value parameter is an error.

    ``--json=JSON``
        Tool parameters as a JSON object. Options given as ``--<param>`` win over the same key in
        the JSON. Use this for nested or associative values. Use it also for a parameter whose
        name is taken by a console option: ``format``, ``json``, ``help``, ``silent``, ``quiet``,
        ``verbose``, ``version``, ``ansi``, ``no-ansi`` and ``no-interaction``.

    ``--format=FORMAT``
        ``pretty`` (default), ``json`` or ``toon``.

    When a parameter name is unknown or a required one is missing, the error message points at
    ``tools:inspect <tool-name>``.

    **Examples:**

    .. code-block:: terminal

        # No parameters
        $ vendor/bin/mate tools:call server-info

        # Parameters as options
        $ vendor/bin/mate tools:call monolog-search --term=error --level=error

        # A boolean as a bare flag
        $ vendor/bin/mate tools:call monolog-search --term="^GET" --regex

        # A variadic parameter
        $ vendor/bin/mate tools:call <tool-name> --tag=a --tag=b

        # Nested values
        $ vendor/bin/mate tools:call <tool-name> --json='{"filters": {"level": "error"}}'

``mate resources:read <uri>``
    Read a resource by its URI. The URI may belong to a static resource or match a resource
    template. For a template, the variables in the URI are passed to the handler.

    **Options:**

    ``--format=FORMAT``
        ``pretty`` (default), ``json`` or ``toon``.

    **Examples:**

    .. code-block:: terminal

        # The collectors a profile has
        $ vendor/bin/mate resources:read symfony-profiler://profile/abc123

        # One collector
        $ vendor/bin/mate resources:read symfony-profiler://profile/abc123/db

Skills
------

See :doc:`skills` for the concepts behind these commands.

Every ``<name>`` argument accepts the installed name (``mate-…``) or the original name of the
skill.

``mate skills:install``
    Rebuild the generated skill folders from the state recorded in ``mate/extensions.php``.
    ``mate discover`` runs it for you.

    ``--dry-run``
        Report what would be installed, rebuilt or removed. Nothing is written.

``mate skills:list``
    List declared and installed skills with their enabled flag, mode, state and status.
    Read-only.

    ``--format=FORMAT``
        ``table`` (default), ``json`` or ``toon``.

``mate skills:validate [<name>]``
    Check the generated folders against the recorded state, and the installed content for dead
    links and weak descriptions. Without a name, all skills are checked. Read-only. Exits with a
    non-zero status when a skill is broken.

    ``--strict``
        Fail on warnings too. Suggestions about a description never change the exit code.

    ``--format=FORMAT``
        ``table`` (default), ``json`` or ``toon``.

``mate skills:prune``
    Remove generated ``mate-*`` folders that no longer belong to any skill.

    ``--dry-run``
        List what would be removed.

``mate skills:override <name>``
    Take ownership of a skill. The package's version is copied into ``mate/skills/<name>/`` and
    the skill switches to ``'mode' => 'override'``.

    ``--force``, ``-f``
        Replace an existing copy in ``mate/skills/``.

``mate skills:reset <name>``
    Hand an overridden skill back to Mate, so it is built from the package again.

    ``--delete-copy``
        Also delete your copy under ``mate/skills/<name>/``. Without it the copy is kept.

``mate skills:disable <name>``
    Hide a skill from coding agents. Its generated folders are removed and it is recorded as
    disabled. The entry stays in ``mate/extensions.php``, and a copy of your own under
    ``mate/skills/`` is left untouched.

``mate skills:enable <name>``
    Make a disabled skill visible again and rebuild its generated folders.

Debugging
---------

``mate debug:capabilities``
    Show all discovered tools, resources and resource templates, grouped by extension. Use it to
    check that an extension registered what you expect, and which package provides a capability.

    **Options:**

    ``--extension=EXTENSION``
        Filter by extension package name. Use ``_custom`` for your own project.

    ``--type=TYPE``
        Filter by capability type: ``tool``, ``resource`` or ``template``.

    ``--format=FORMAT``
        ``text`` (default), ``json`` or ``toon``.

    **Examples:**

    .. code-block:: terminal

        $ vendor/bin/mate debug:capabilities
        $ vendor/bin/mate debug:capabilities --type=tool
        $ vendor/bin/mate debug:capabilities --extension=symfony/ai-monolog-mate-extension
        $ vendor/bin/mate debug:capabilities --extension=_custom

``mate debug:extensions``
    Show the discovered extensions with their scan directories, include files and instructions
    file. Use it to find out why an extension provides no capabilities.

    **Status indicators:**

    ``[enabled]``
        The extension is enabled in ``mate/extensions.php``.

    ``[loaded]``
        The extension was loaded into the DI container.

    ``[not loaded]``
        The extension is configured but could not be loaded, for example because the package was
        removed.

    **Options:**

    ``--show-all``
        Include disabled extensions.

    ``--format=FORMAT``
        ``text`` (default), ``json`` or ``toon``.
