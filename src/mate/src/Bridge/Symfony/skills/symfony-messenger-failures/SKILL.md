---
name: symfony-messenger-failures
description: Diagnose Messenger messages that fail, a worker (messenger:consume) that keeps failing, or a failure transport that keeps growing. Groups every failed message by cause, so a small real bug is not hidden behind a flood of one transient failure. Use instead of paging through messenger:failed:show; works when the kernel does not boot.
---

# Messenger failures

`symfony-messenger-failed` reads the failure transports straight from their storage and groups the
messages by cause: exception class, message pattern (quoted values, paths and ids blanked) and
the application frame that threw.

```
vendor/bin/mate tools:call symfony-messenger-failed
vendor/bin/mate tools:call symfony-messenger-failed --transport=failed --group=3f2a9c1e    # the messages of one group
```

With several kernel contexts (one cache directory each), add `--context=<name>`.

The result has one entry per failure transport under `transports` (`--transport=<name>` reads one).
Per transport: `message_count`, `scanned`, `scan_truncated` (with `scan_truncated_reason` when
not every message was read), `group_count`, `undecodable` (rows it could not read, with the
reason) and `groups`, largest first. At most 50 groups are listed: `groups_truncated`,
`omitted_groups` and `omitted_messages` count the rest. A transport it cannot read has an `error`
instead. Per group: `group_id` (stable, pass it as `--group`), `count`, `message_classes`, `exception_class`,
`failed_in` (first frame outside vendor/, usually the handler), `sample_messages` (distinct
exception messages, the actual values are here), `trace` (top frames), `retry_count` (a number, or
`min`/`max`), `first_failed_at` / `last_failed_at`, `original_transports`, `ids`. With `--group`, up to 100 `messages` (`messages_truncated` when there are more).

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
