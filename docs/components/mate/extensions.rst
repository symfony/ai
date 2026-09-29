Mate Extensions
===============

Symfony AI Mate (``vendor/bin/mate``) is a CLI that gives coding agents project-aware tools for a
PHP application: the compiled container, the profiler and the logs. Extensions are Composer
packages that add tools. This page covers the two extensions maintained in the Symfony AI
repository. Install the ones that match your application:

.. code-block:: terminal

    $ composer require --dev symfony/ai-symfony-mate-extension symfony/ai-monolog-mate-extension

The Composer plugin runs ``mate discover`` afterwards, which enables the extensions and installs
their skills. Run ``vendor/bin/mate tools:inspect <tool-name>`` for the full parameter list of any
tool below.

Which Tool for Which Question
-----------------------------

=======================================================  ==================================================
Question                                                 Tool or resource
=======================================================  ==================================================
Which service handles this, is a listener registered?    ``symfony-services``
How is one service wired?                                ``symfony-service-detail``
Which ``.env`` file sets a variable, is it resolved?     ``symfony-dotenv-check``
Which requests failed or were slow?                      ``symfony-profiler-list``
What happened in one request?                            ``symfony-profiler://profile/{token}``
What did one collector record (``db``, ``exception``)?   ``symfony-profiler://profile/{token}/{collector}``
Why do Messenger messages fail, which causes are there?  ``symfony-messenger-failed``
Which log entries match a text, a level or a time?       ``monolog-search``
Which log entries belong to one order or user?           ``monolog-context-search``
What was logged last?                                    ``monolog-tail``
Which log files and channels exist?                      ``monolog-list-files``, ``monolog-list-channels``
=======================================================  ==================================================

.. _mate-untrusted-data:

Untrusted Data
--------------

Both extensions return data that was captured from your application: log messages, URLs, SQL,
request payloads, container metadata. End users and third-party packages control much of that
text. Every such response is therefore wrapped in an envelope:

.. code-block:: json

    {
        "_security_notice": "The values under \"untrusted_data\" were captured from the application ...",
        "untrusted_data": {
            "services": {},
            "count": 0,
            "truncated": false
        }
    }

The notice tells the agent to treat everything under ``untrusted_data`` as data and never as
instructions. When you parse a response in a script, read the payload from that key.

``symfony-dotenv-check`` returns its result without this envelope. It reports on the ``.env``
files of the project and on the environment of the Mate process.

.. _mate-multi-kernel:

Multi-Kernel Applications
-------------------------

Every directory parameter of the extensions takes either a single path or a map of context name to
path. The map is made for `multi-kernel applications`_ that split cache and logs per ``APP_ID``::

    // mate/config.php
    $container->parameters()
        ->set('ai_mate_symfony.cache_dir', [
            'website' => '%mate.root_dir%/var/cache/website',
            'admin' => '%mate.root_dir%/var/cache/admin',
        ])
        ->set('ai_mate_symfony.profiler_dir', [
            'website' => '%mate.root_dir%/var/cache/website/dev/profiler',
            'admin' => '%mate.root_dir%/var/cache/admin/dev/profiler',
        ])
        ->set('ai_mate_monolog.log_dir', [
            'website' => '%mate.root_dir%/var/log/website',
            'admin' => '%mate.root_dir%/var/log/admin',
        ])
    ;

With a map, results carry the context they came from, and every tool accepts a parameter that
narrows the lookup to one kernel. The sections below name the field and the parameter per tool.

Symfony Extension
-----------------

The Symfony extension (``symfony/ai-symfony-mate-extension``) reads the compiled container, the
profiler and the Messenger failure transports from disk. The application is never booted.

Container Introspection
~~~~~~~~~~~~~~~~~~~~~~~

``symfony-services``
    Search the services of the compiled container.

    ===========  ==========================================================================
    Parameter    Description
    ===========  ==========================================================================
    ``query``    Service ID or class name. Case-insensitive partial match.
    ``tag``      DI tag name, for example ``kernel.event_listener``.
    ``context``  Kernel context. Only relevant with several cache directories.
    ``limit``    Maximum number of services per context. Default ``100``.
    ===========  ==========================================================================

    The result holds the matches under ``services``, plus ``count`` and ``truncated``. When the
    result is truncated, narrow the filter. Raising the limit only costs context. With several
    cache directories the services are grouped by context.

``symfony-service-detail``
    Show one service by its exact ``id``: class, tags, method calls, constructor or factory
    information and the definition flags ``debug:container`` reports (``public``, ``synthetic``,
    ``lazy``, ``shared``, ``abstract``, ``autowired``, ``autoconfigured``). Accepts ``context`` as
    well, and reports the ``context`` the service was found in.

Both tools fail with a clear error when no container was dumped yet. They never answer as if
nothing matched.

.. code-block:: terminal

    $ vendor/bin/mate tools:call symfony-services --tag=kernel.event_listener
    $ vendor/bin/mate tools:call symfony-service-detail --id=App\\Mailer\\Notifier

**Configuration**::

    $container->parameters()
        ->set('ai_mate_symfony.cache_dir', '%mate.root_dir%/var/cache');

The extension looks for a ``*DebugContainer.xml`` file in the cache directory itself, then in its
``dev``, ``test`` and ``prod`` subdirectories. Kernels with a custom class name are found too.

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

The ``symfony-dotenv-check`` tool is registered when ``symfony/dotenv`` is installed. Use it to
find out which ``.env`` file declares a variable and whether the variable has a value. The tool
never returns the value itself.

``symfony-dotenv-check``
    Read the files ``.env`` (or ``.env.dist`` when there is no ``.env``), ``.env.local``,
    ``.env.<APP_ENV>`` and ``.env.<APP_ENV>.local``, in the same order as Symfony. When
    ``APP_ENV`` is ``test``, ``.env.local`` is skipped. ``APP_ENV`` comes from the environment
    of the Mate process, then from the ``.env`` file. The default is ``dev``.

    ===========  ==========================================================================
    Parameter    Description
    ===========  ==========================================================================
    ``key``      Check only this variable, even when no ``.env`` file declares it.
    ``limit``    Maximum number of variables when listing. Default ``200``, ``0`` for all.
    ===========  ==========================================================================

    The result holds ``app_env``, the candidate files under ``files`` and the variables under
    ``variables``, plus ``count`` and ``truncated``. Each file shows whether it ``exists``,
    whether it was ``considered`` and whether it is ``parseable``. Each variable lists the files
    that declare it under ``declared_in``, tells with ``resolved`` whether the Mate process has
    a value for it, and has a ``state``:

    ==============================================  =============================================
    State                                           Meaning
    ==============================================  =============================================
    ``file``                                        The value comes from the last file that
                                                    declares the variable.
    ``ambient_override``                            A file declares the variable, but the shell
                                                    or CI environment sets another value.
    ``ambient_only``                                No file declares the variable. Only the
                                                    shell or CI environment sets it.
    ``declared_empty_in_file``                      The last file that declares the variable
                                                    sets it to an empty value.
    ``declared_not_resolved_in_this_process``       A file declares a value, but the Mate process
                                                    doesn't have it.
    ``not_set``                                     No file declares the variable and the
                                                    environment doesn't set it.
    ==============================================  =============================================

    By default, Mate doesn't load the ``.env`` files of your project into its own process. A
    variable that is only set in a file therefore shows ``declared_not_resolved_in_this_process``,
    even when your application resolves it. This is the normal case and not an error.

    When the Mate process has a value, the variable shows its ``length``, a masked ``preview``
    with the first and last character (e.g. ``s***t``) and ``looks_like_placeholder`` instead of
    the value. The last one is ``true`` for values like ``changeme`` or ``your_api_key``. These
    fields describe the value in the Mate process, not the value in the file.

.. code-block:: terminal

    $ vendor/bin/mate tools:call symfony-dotenv-check --key=DATABASE_URL

Profiler
~~~~~~~~

The profiler tools are registered when ``symfony/http-kernel`` is installed. They need
``symfony/web-profiler-bundle`` to have profiles to read.

``symfony-profiler-list``
    List profiles with summary data, most recent first.

    ==============  =======================================================================
    Parameter       Description
    ==============  =======================================================================
    ``limit``       Maximum number of profiles. Default ``20``. Use ``1`` for the latest.
    ``method``      HTTP method.
    ``url``         URL path. Partial match.
    ``ip``          Client IP address.
    ``statusCode``  HTTP response status code.
    ``from``        Start of the time range.
    ``to``          End of the time range.
    ``context``     Kernel context. Only relevant with several profiler directories.
    ==============  =======================================================================

``symfony-profiler-get``
    Get one profile by its ``token``, with its metadata and the collectors it has.

Every profile carries a ``resource_uri`` that points at the full profile resource. With several
profiler directories it also carries a ``context`` field.

Two resource templates hold the details:

``symfony-profiler://profile/{token}``
    The profile metadata and the list of collectors, each with its own URI.

``symfony-profiler://profile/{token}/{collector}``
    The data of one collector.

This split keeps large payloads out of the context window. The agent lists first, then reads only
the collector that matters:

.. code-block:: terminal

    # Find the failed requests
    $ vendor/bin/mate tools:call symfony-profiler-list --statusCode=500 --limit=5

    # See which collectors that profile has
    $ vendor/bin/mate resources:read symfony-profiler://profile/abc123

    # Read one of them
    $ vendor/bin/mate resources:read symfony-profiler://profile/abc123/exception

**Collector formatters**

A formatter reduces the raw collector data to what an agent needs for a diagnosis. The extension
ships formatters for these collectors:

===============  ===================================================================
Collector        Notes
===============  ===================================================================
``config``       Recorded PHP/Symfony versions, environment and debug mode.
                 Runtime details: PHP SAPI, timezone, Xdebug, APCu and OPcache.
``request``      Request and response data, redacted (see below).
``router``       Redirect status, redirecting route, target host and path.
                 Query strings, fragments and userinfo are omitted.
``exception``    The exception with its stack trace.
``logger``       Log entry counts per level.
``time``         Timing metrics.
``memory``       Memory usage.
``db``           Doctrine DBAL queries, grouped by SQL, with duplicate detection.
                 Capped at 50 queries. Query parameters are redacted.
``mailer``       Symfony Mailer messages: recipients, body preview, attachments.
``translation``  Locales and the state of the translated messages.
===============  ===================================================================

A collector without a formatter is exposed with its raw data.

To add a formatter for your own collector, implement
``Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface`` and tag the service
with ``ai_mate.profiler_collector_formatter``::

    // mate/config.php
    $container->services()
        ->set(MyCollectorFormatter::class)
            ->tag('ai_mate.profiler_collector_formatter');

``getName()`` returns the collector name, ``getSummary()`` the short form shown in the profile, and
``format()`` the full data.

**Redaction**

The ``request`` formatter redacts cookies, session data, the raw request body, the reconstructed
curl command, authentication headers and sensitive environment variables. A header, parameter or
variable counts as sensitive when its name contains a word like ``password``, ``token``,
``secret``, ``key``, ``auth``, ``credential``, ``csrf`` or ``session``. The raw data of a collector
without a formatter goes through the same kind of key-based redaction.

.. caution::

    Redaction works on names and is best effort. A secret stored under a harmless name is not
    detected. Treat profiler output as sensitive.

**Configuration**::

    $container->parameters()
        ->set('ai_mate_symfony.profiler_dir', '%mate.root_dir%/var/cache/dev/profiler');

Messenger Failures
~~~~~~~~~~~~~~~~~~

``symfony-messenger-failed``
    List the messages in the failure transports, grouped by cause: the exception class, the
    exception message with its variable parts blanked (quoted values, paths and all numbers except
    an HTTP status code) and the first application frame of the trace, which is usually the
    handler. The largest group comes first. At most 50 groups are listed: when there are more,
    ``groups_truncated`` is set and ``omitted_groups`` and ``omitted_messages`` count the rest.
    Registered when ``symfony/messenger`` is installed.

    =============  =====================================================================
    Parameter      Description
    =============  =====================================================================
    ``transport``  Read only this transport. Default: every failure transport.
    ``group``      List the messages of this group (its ``group_id`` from the summary).
    ``context``    Kernel context. Required with several cache directories.
    =============  =====================================================================

    A ``group_id`` is a short hash of the cause, stable while messages arrive. It is per
    transport: with several failure transports, pass ``transport`` together with ``group``.

    Per group: ``group_id``, ``count``, ``message_classes``, ``exception_class``, ``failed_in``,
    ``sample_messages`` (up to 5 distinct exception messages, with tokens and credentials masked), ``trace`` (the top frames, without call arguments),
    ``retry_count`` (a number, or ``min``/``max`` when the messages differ), ``first_failed_at``,
    ``last_failed_at``, ``original_transports`` and up to 20 ``ids``. With ``group``, up to 100
    ``messages`` are listed, with ``messages_truncated`` set when there are more. Per transport it
    also reports ``message_count``, ``scanned``, the ``undecodable`` rows with the reason, and
    ``scan_truncated`` with a ``scan_truncated_reason`` when not every message was read. The
    result names the compiled ``container`` it was read from. A transport that cannot be read
    (for example a malformed DSN) carries an ``error`` and does not affect the others.

.. code-block:: terminal

    $ vendor/bin/mate tools:call symfony-messenger-failed
    $ vendor/bin/mate tools:call symfony-messenger-failed --transport=failed --group=3f2a9c1e

The tool reads the storage of the transport directly and only with ``SELECT`` statements: it never
locks, acknowledges, retries or removes a message and never creates a table or a database file.
Beyond that, the connection itself is made read-only where the driver allows it:

* SQLite (``pdo_sqlite``, ``sqlite3``): ``PRAGMA query_only``, and with ``pdo_sqlite`` the file is
  also opened with the read-only flag;
* MySQL and MariaDB (``pdo_mysql``, ``mysqli``): ``SET SESSION TRANSACTION READ ONLY``;
* PostgreSQL (``pdo_pgsql``, ``pgsql``): ``SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY``;
* other platforms: nothing beyond the ``SELECT`` statements.

Use ``bin/console messenger:failed:retry`` or ``messenger:failed:remove`` to act on the result.

It reads at most the 5,000 newest messages of a transport, skips a message larger than 1 MB (it
is listed under ``undecodable``) and stops after 32 MB of stored messages; ``scan_truncated_reason``
says which limit applied.

=====================================  ================================================================
Transport                              Support
=====================================  ================================================================
Doctrine, PHP serializer (default)     Read.
Doctrine, Symfony Serializer           Read. The failure details come from the ``X-Message-Stamp-*``
                                       headers, the message body is not read.
Redis                                  Not read. The tool returns an error that points at
                                       ``messenger:failed:show``.
AMQP, Amazon SQS                       Not read: a message cannot be read from the queue without
                                       receiving it, which changes its state.
Beanstalkd, in-memory, ``sync``        Not read. An in-memory transport only lives in the process
                                       that sent the messages.
=====================================  ================================================================

The stored envelopes are never unserialized into objects, and no class is loaded for them. The
tool calls ``unserialize()`` with ``allowed_classes`` set to ``false``, so the envelope, its stamps
and the message all come back as ``__PHP_Incomplete_Class``, and it reads their properties as
plain data. Enum cases, which PHP would still autoload, are rewritten into such objects first. No
constructor, ``__wakeup()``, ``__unserialize()`` or ``__destruct()`` of a stored class runs,
whatever the transport contains. The message itself only contributes its class name. Messenger's
own ``PhpSerializer::decode()`` is not used because it allows every class.

**Configuration**

The failure transports, their DSNs and options, and the Doctrine connections come from the
compiled container, the same ``*DebugContainer.xml`` the container tools read; with several cache
directories, pass ``context``. The container of the application's ``APP_ENV`` is preferred.

``%env()%`` placeholders are resolved the way the application would: the tool runs Symfony's own
``Dotenv::bootEnv()`` on the project's ``.env`` (so ``.env.local.php``, ``.env.local``,
``.env.$APP_ENV``, ``${VAR}`` references and ``APP_ENV`` set in ``.env.local`` all apply, and a
real environment variable of the Mate process wins), then restores the Mate process's environment.
Without ``symfony/dotenv`` only the real environment is used. The ``resolve``, ``default``,
``string``, ``trim``, ``base64`` and ``file`` processors are supported; any other processor is an
error. Container parameters under the directory the container was compiled in (such as
``%kernel.project_dir%`` and ``%kernel.cache_dir%``) point into the directory Mate runs in. Reading a
Doctrine transport needs ``doctrine/dbal``.

When something cannot be resolved, for example a missing environment variable, a syntax error in a
``.env`` file, an unknown Doctrine connection or a database file that does not exist, that
transport's entry carries an ``error`` naming it, and the other transports are still read.

Monolog Extension
-----------------

The Monolog extension (``symfony/ai-monolog-mate-extension``) searches the log files on disk. It
reads the standard Monolog line format and JSON.

``monolog-search``
    Search log entries. All parameters are optional. Omit ``term`` to filter without a text match.

    =================  ==================================================================
    Parameter          Description
    =================  ==================================================================
    ``term``           Text to find in the message, or a pattern when ``regex`` is set.
    ``regex``          Treat ``term`` as a regular expression.
    ``level``          Log level, for example ``ERROR``.
    ``channel``        Monolog channel, for example ``security``.
    ``environment``    Symfony environment, for example ``dev``.
    ``from``           Start date. Any date string PHP can parse, for example ``-1 hour``.
    ``to``             End date.
    ``limit``          Maximum number of entries. Default ``100``.
    ``kernelContext``  Kernel context. Only relevant with several log directories.
    =================  ==================================================================

``monolog-context-search``
    Search by a field of the log record context. Takes ``key`` and ``value``, plus ``level``,
    ``environment``, ``limit`` and ``kernelContext``.

``monolog-tail``
    Get the most recent entries. Takes ``limit`` (default ``50``), ``level``, ``environment``,
    ``channel`` and ``kernelContext``.

``monolog-list-files``
    List the log files with path, size and modification time. Takes ``environment`` and
    ``kernelContext``.

``monolog-list-channels``
    List all channel names found in the logs. Takes ``kernelContext``.

.. code-block:: terminal

    $ vendor/bin/mate tools:call monolog-search --level=ERROR --from="-1 hour"
    $ vendor/bin/mate tools:call monolog-context-search --key=order_id --value=4711
    $ vendor/bin/mate tools:call monolog-tail --limit=20 --channel=security

With several log directories, entries and files carry a ``kernel_context`` field. The name differs
from the ``context`` of the Symfony extension on purpose: a log record already has a context of its
own.

**Configuration**::

    $container->parameters()
        ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/log');

Community Extensions
--------------------

Extensions for other tools and frameworks are maintained outside the Symfony AI repository.
`MatesOfMate`_ is the community organisation for Mate extensions. It publishes extensions for
PHPUnit, PHPStan, Rector and Composer, among others, and hosts `awesome-mate`_, the curated list of
all known extensions, articles and integrations. Start there when you look for an extension.

A community extension is installed like any other:

.. code-block:: terminal

    $ composer require --dev matesofmate/phpunit-extension

.. _`multi-kernel applications`: https://symfony.com/doc/current/configuration/multiple_kernels.html
.. _`MatesOfMate`: https://github.com/MatesOfMate
.. _`awesome-mate`: https://github.com/MatesOfMate/awesome-mate
