---
name: symfony-messenger-failures
description: Diagnose Messenger messages that fail, a worker (messenger:consume) that keeps failing, or a failure transport that keeps growing. Groups every failed message by cause, so a small real bug is not hidden behind a flood of one transient failure. Use instead of paging through messenger:failed:show; works when the kernel does not boot.
---

# Messenger failures

`symfony-messenger-failed` reads the failure transports straight from their storage and groups the
messages by cause: exception class, message pattern (quoted values, paths and numbers blanked) and
the application frame that threw.

```
vendor/bin/mate tools:call symfony-messenger-failed
vendor/bin/mate tools:call symfony-messenger-failed --transport=failed --group=2    # every message of group 2
```

The result has one entry per failure transport under `transports` (`--transport=<name>` reads one).
Per transport: `message_count`, `group_count`, `undecodable` (rows it could not read, with the
reason) and `groups`, largest first. Per group: `count`, `message_classes`, `exception_class`,
`failed_in` (first frame outside vendor/, usually the handler), `sample_messages` (distinct
exception messages, the actual values are here), `trace` (top frames), `retry_count` (a number, or
`min`/`max`), `first_failed_at` / `last_failed_at`, `original_transports`, `ids`.

## Reading it

1. Look at every group, not only the largest. A transient outage (a share not mounted, an API
   down) produces many identical failures; a code bug that only hits some inputs produces a few.
2. Tell environment from code: a failure in I/O against something outside the app (a path that
   does not exist here, a refused connection, a timeout) is environment; a failure in the app's
   own logic on the message data (validation, parsing, a type error) is a code bug.
3. For a code bug, `sample_messages` show the inputs that break it; open `failed_in` and fix the
   code for all of them, not only the first sample.
4. `messenger:failed:retry <id>` re-dispatches a message after the fix; this tool never changes
   the transport.

## Limits

Doctrine transports only, with the PHP serializer or the Symfony Serializer. For Redis, AMQP, SQS,
Beanstalkd and in-memory transports the entry carries an `error`; use
`bin/console messenger:failed:show --transport=<name>` there. An `error` about a missing
environment variable or database means the tool could not locate the storage, not that nothing
failed.
