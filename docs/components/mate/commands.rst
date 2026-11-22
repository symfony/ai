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
    * rewrites ``mate/AGENT_INSTRUCTIONS.md``, the managed block in ``AGENTS.md`` and, if
      you have a ``CLAUDE.md``, the ``@AGENTS.md`` import in it

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

These are the five commands a coding agent uses.

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

``mate tools:call-batch``
    Execute several tools and resource reads in one command call. Useful when one investigation needs
    results from multiple tools or resources together (e.g. correlating a profiler request with logs
    from the same time window): a single call replaces one ``tools:call`` or ``resources:read`` per item. Calls run sequentially, in array
    order, and one call failing does not abort the others.

    The named tools run exactly as given. The batch makes no read-only promise, so only batch
    tools you would also call one by one. The command exits with a non-zero code when any call
    failed. A call object is either a tool call (``tool`` and optional ``params``) or a
    resource read (``resource`` with a URI). Any other key is rejected.

    **Options:**

    ``--json=JSON``
        A JSON array of ``{"tool": "...", "params": {...}}`` or ``{"resource": "<uri>"}`` call objects
        (``params`` is optional for a tool that takes none). A call may also carry ``id``,
        ``output`` and ``pick`` (see below).

    ``--format=FORMAT``
        ``pretty`` (default, one section per tool), ``json`` or ``toon``.

    **Result shape:**

    A JSON array, one entry per call, in the same order as the request:
    ``{"tool": "...", "ok": true|false, "result": <decoded tool output>|null, "error": "..."|null}``.
    A resource read reports ``resource`` with the URI instead of ``tool``. An entry also has ``id``
    when the call had one, and ``missing_paths`` when a ``pick`` path found nothing.

    **Chaining calls:**

    ``id``
        Names a call so later calls can use its result. Lowercase letters, digits and ``_``, starting
        with a letter, unique in the batch.

    ``{{id.path}}``
        Anywhere inside ``params`` (any depth) or inside a ``resource`` URI, a string like this is
        replaced by a value of an earlier call. If the string is exactly one expression, the value
        keeps its type (a number stays a number, a list stays a list). Inside a longer string the
        value is inserted as text, which only works for scalars. Write ``\{{`` for a literal ``{{``.
        A reference may only point to an earlier call that has an ``id``. An unknown id or a forward
        reference is rejected before anything runs.

    Paths use ``a.b`` for fields, ``[0]`` for one list element, ``[*]`` for all elements (the rest of
    the path is applied to each) and ``[:3]`` for the first three. Paths are resolved against
    the full result of the earlier call, also when that call uses ``pick`` or ``output: false``. If a
    result is wrapped in ``untrusted_data``, paths start inside it, so ``list.profiles[0].token``
    needs no ``untrusted_data`` prefix.

    When a reference cannot be resolved (empty list, missing field, the referenced call failed), the
    dependent call does not run. Its entry is ``ok: false`` and the error names the path, the reason
    and the keys available at that level. Other calls continue. A call that depends on such a call
    fails with ``Depends on failed call "<id>".``.

    ``output``
        ``false`` hides the entry from the output. The call still runs and can be referenced. A failed
        call is shown anyway and still makes the exit code non-zero.

    ``pick``
        A non-empty list of paths. Only these values are kept: the result becomes an object keyed
        by the path strings. With an ``untrusted_data`` envelope, ``_security_notice`` stays and
        the content of ``untrusted_data`` is replaced. Paths without a value are null and listed
        in ``missing_paths``. Works for tool calls and resource reads.

    **Examples:**

    .. code-block:: terminal

        # Correlate a slow request with logs from the same time window
        $ vendor/bin/mate tools:call-batch --json='[{"tool": "symfony-profiler-get", "params": {"token": "abc123"}}, {"tool": "monolog-search", "params": {"level": "ERROR"}}]'

        # Read several resources of one profile together with a tool call
        $ vendor/bin/mate tools:call-batch --json='[{"tool": "symfony-profiler-list", "params": {"limit": 1}}, {"resource": "symfony-profiler://profile/abc123/db"}, {"resource": "symfony-profiler://profile/abc123/time"}]'

        # Chain: list a profile, fetch it by the token from the list, keep two fields
        $ vendor/bin/mate tools:call-batch --json='[{"id": "list", "tool": "symfony-profiler-list", "params": {"limit": 1}, "output": false}, {"tool": "symfony-profiler-get", "params": {"token": "{{list.profiles[0].token}}"}, "pick": ["request.status_code", "request.path"]}]'

        # JSON output format
        $ vendor/bin/mate tools:call-batch --json='[{"tool": "server-info"}]' --format=json

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

    ``--format=FORMAT``
        ``table`` (default), ``json`` or ``toon``.

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
