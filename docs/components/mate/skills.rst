Skills
======

Symfony AI Mate (``vendor/bin/mate``) is a CLI that gives coding agents project-aware tools for a
PHP application: the compiled container, the profiler and the logs.

`Agent Skills <https://agentskills.io>`_ are ``SKILL.md`` files that give a coding agent
structured, multi-step knowledge for a task. Extensions ship skills next to their tools, and Mate
installs them where coding agents read them.

Skills are what make the CLI findable. The agent instructions tell an agent that Mate exists. The
skills tell it *when* to reach for which tool and in what order. That is the difference between
Mate being installed and Mate being used.

Installing Skills
-----------------

You usually do not run anything. ``mate discover`` installs the skills of every enabled extension,
and the Composer plugin runs ``discover`` after ``composer install`` and ``composer update``. To
sync by hand:

.. code-block:: terminal

    $ vendor/bin/mate skills:install

To see what an install would do, run it with ``--dry-run``. The same reconciler runs and reports
what it would install, rebuild or remove, but nothing is written.

Where Skills Are Installed
--------------------------

Each skill is installed under a ``mate-`` prefixed directory name, for example
``mate-demo-skill``. The prefix avoids clashes with skills from other sources. The ``name`` in the
installed ``SKILL.md`` is rewritten to match. Skills land in two locations:

* ``.agents/skills/`` is the source of truth. Codex, OpenCode and GitHub Copilot read it directly.
* ``.claude/skills/`` mirrors it through relative symlinks, because Claude Code only reads its own
  directory. Where symlinks are unavailable, the mirror is a copy.

Both folders are generated output. ``skills:install`` rebuilds them from source on every run and
removes skills that are gone or disabled. Do not edit them by hand. Your changes are overwritten
on the next run, and ``mate skills:validate`` reports them as errors. To change a skill, see
`Overriding a Skill`_.

Skills are copied and never symlinked into ``vendor/``. What your agent loads is a real file you
can open and diff, and a package update cannot change it underneath you.

.. tip::

    Commit the generated folders. An upstream skill change then shows up as a reviewable diff
    instead of landing silently. Mate does not touch your ``.gitignore``.

Skill State
-----------

All skill state lives in ``mate/extensions.php``. Two keys per skill carry your intent:

``enabled``
    Whether the skill is installed at all.

``mode``
    ``managed`` means Mate builds the skill from the package. ``override`` means you own it, and
    Mate builds from your copy in ``mate/skills/<name>/``.

Everything else is written by Mate and rewritten on every install: the resulting ``state``
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

Prefer the ``skills:*`` commands over editing the two keys. They also reinstall, so the recorded
state never falls out of step with your intent. Editing by hand works as well, and the next
install picks the change up.

Disabling a Skill
-----------------

.. code-block:: terminal

    $ vendor/bin/mate skills:disable symfony-log-investigation
    $ vendor/bin/mate skills:enable symfony-log-investigation

A disabled skill loses its generated folders but keeps its entry in ``mate/extensions.php``.

Overriding a Skill
------------------

.. code-block:: terminal

    $ vendor/bin/mate skills:override symfony-log-investigation

This copies the package's version into ``mate/skills/symfony-log-investigation/`` and switches the
skill to ``override``. Edit that copy. Mate builds the generated folders from it and never writes
into ``mate/skills/``.

To hand the skill back to Mate:

.. code-block:: terminal

    $ vendor/bin/mate skills:reset symfony-log-investigation

Your copy is kept unless you pass ``--delete-copy``.

Checking Skills
---------------

``mate skills:list`` gives an overview. ``mate skills:validate`` checks the generated folders
against the record in ``mate/extensions.php``. It reports:

* hand-edited content
* missing folders and a mirror that points at the wrong place
* sources that changed since the last install
* a Markdown link in ``SKILL.md`` that points at a file outside the skill (a warning)
* a description that is shorter than 40 characters or never says when the skill applies
  (a suggestion)

The description matters because it is all an agent has when it decides whether to load the skill.
Suggestions are printed but never change the exit code, not even with ``--strict``.

``mate skills:prune`` removes leftover ``mate-*`` folders that belong to no skill.

Available Skills
----------------

A skill ships with the extension whose tools it drives, so a project only installs skills it can
follow.

The core package ships two:

``php-environment-check``
    Find out whether a failing tool is caused by the PHP runtime and not by the application.

``system-information``
    Resolve which dependency versions are installed, through ``composer show`` and
    ``composer.lock``.

The Symfony extension adds three:

``symfony-request-triage``
    Decide which of the other skills a given symptom calls for.

``symfony-profiler-debugging``
    Diagnose a request that failed or was slow, through the profiler: which profile to find, which
    collectors to read, and in what order for which symptom.

``symfony-service-inspection``
    Inspect the compiled DI container when the wiring is the suspect, not the code.

The Monolog extension adds one:

``symfony-log-investigation``
    Investigate trends across requests in the Monolog log files, as opposed to one failed request.

To ship skills with your own extension, see :doc:`creating-extensions`.
