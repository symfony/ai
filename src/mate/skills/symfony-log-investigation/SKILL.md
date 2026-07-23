---
name: symfony-log-investigation
description: Investigate an error or behavior across Monolog log files when there is no single profile to read, or when tracking something over time or across requests. Use for "when did this start", "how often", or following one request/user through the logs. For a single known request that still has a profile, prefer profiler debugging.
---

# Log investigation

Reads Monolog files through Mate's CLI. Entries come back as `{datetime, channel, level, message, context, extra, source_file, line_number}`. Sensitive context keys are redacted in the output but still matched when you search them.

- `monolog-list-files` (opt `environment`): what log files exist, newest first.
- `monolog-list-channels`: distinct channel names (`app`, `security`, `doctrine`, ...). Reads every file, so it is the slow one; skip it if you already know the channel.
- `monolog-tail` (`lines`, `level`, `environment`, `channel`): most recent entries. Reads ONLY the single newest file.
- `monolog-search` (`term`, `regex`, `level`, `channel`, `environment`, `from`, `to`, `limit`): searches across all files. Empty `term` with filters set = filter-only.
- `monolog-context-search` (`key`, `value`, `level`, `environment`, `limit`): matches a structured context field. No channel or date filter here.

## Workflow

1. Orient: `mate tools:call monolog-list-files`. Confirm the environment you care about is present and recently modified.
2. Latest state: `mate tools:call monolog-tail --level=error --lines=50`. Good for "what just broke", nothing else.
3. Narrow with search:
   - `mate tools:call monolog-search --term="Timeout" --level=error`
   - Time-box it: `--from="-1 hour"`, `--from=2026-07-01 --to=2026-07-02`. Any PHP-parseable date works.
   - Regex: `mate tools:call monolog-search --term="user \d+ locked" --regex`. A bare pattern is wrapped as `/.../i`; pass your own `/.../` or `#...#` to control anchoring and flags.
4. Pivot on a field: once you have an identifier (request id, user id, order id), follow it with `mate tools:call monolog-context-search --key=request_id --value=abc123`. This is how you reconstruct one request or one user across many lines.

## Reading

- Correlate `channel` + `level` + `datetime`. The channel tells you the subsystem (`doctrine` = DB, `security` = auth, `request`/`php` = framework), the level tells you severity, the timestamp anchors it to a deploy or an incident.
- A cluster of same-`message` errors starting at one timestamp is the onset. Use it as the `--from` to see what preceded it.
- `source_file` + `line_number` point back into the log file, not your application code.

## Failure paths

- No matches: widen before concluding. Drop `--level`, widen the date window, try a shorter or partial `term`. `level` matches exactly (case-insensitive); `WARN` will not match `WARNING`.
- Wrong channel: `--channel` is an exact (case-insensitive) name. Run `monolog-list-channels` if unsure rather than guessing.
- `monolog-tail` looks empty or stale: it only reads the newest file. Rotated history (`prod-2026-07-01.log`) is invisible to tail; use `monolog-search` with a date range to reach it.
- Nothing logged at all: the app may log to stderr/syslog rather than a file, or the level threshold filters it out before it is written. The profiler `logger` collector still captures per-request logs even when file logging is quiet.
