---
name: symfony-profiler-debugging
description: Diagnose a Symfony request that failed, errored (5xx), or was slow, using the profiler. Use when a specific request misbehaved and the app has the profiler enabled (usually dev). Not for cross-request trends over time (use log investigation) or DI wiring bugs (use service inspection).
---

# Profiler debugging

Reads the profiler through Mate's CLI. Two tools, two resources:

- `symfony-profiler-list` filters profiles (`method`, `url`, `ip`, `statusCode`, `context`, `from`, `to`, `limit`). Newest first, so `--limit=1` is the latest. Returns summaries with a `resource_uri` per profile.
- `symfony-profiler-get --token=<t>` returns one profile's metadata. It does NOT list collectors.
- `symfony-profiler://profile/{token}` lists the collectors this profile actually has, each with its URI.
- `symfony-profiler://profile/{token}/{collector}` returns that collector, as `{name, data, summary}`. `summary` is the triage view, `data` the full detail.

## Workflow

1. Find the profile. Do not scroll all profiles.
   - Error: `mate tools:call symfony-profiler-list --statusCode=500 --limit=5`
   - Known URL: `--url=/checkout`. Latest request: `--limit=1`.
2. `mate resources:read symfony-profiler://profile/<token>` to see which collectors exist. Apps differ; only read what is present.
3. Read collectors in diagnosis order, not all of them.

## Reading order

Branch on the symptom.

**Errored (5xx / exception):** read `exception` first.
`data.class`, `message` (secrets scrubbed), `file`, `line`, and `trace` (top 10 frames) are the fix. If `has_exception` is false on an error response, the failure is upstream: check `request` for the status and `logger` for what was logged.

**Slow but succeeded:** skip `exception`, go to `time`, then `db`, then `memory`.
- `time`: `duration_ms` is total. `events` are sorted slowest first with a `category` (e.g. `doctrine`, `template`, `controller`) that tells you which subsystem ate the time. That category picks the next collector.
- `db`: `query_count` plus `summary.duplicate_query_count`. `queries` are grouped by identical SQL, sorted by `total_time_ms`. An N+1 shows as one grouped entry with a high `count` (same statement fired in a loop), or a high `duplicate_query_count`. One slow statement shows as a high `avg_time_ms`. `sample_params` shows what was bound. Grouped list caps at 50 (`queries_truncated`).
- `memory`: `usage_percent` against the limit. Only relevant when the request is heavy or OOMs.

**Wrong output / bad input:** read `request`.
`summary` has `method`, `path`, `route`, `status_code`, `content_type`. `data` has the sanitized bags (`request_query`, `request_request`, `request_headers`, `session_attributes`). Sensitive values, the raw body, and the curl command are redacted or omitted by design, so do not expect to read a password back.

**Tie a log line to the request:** `logger` has `error_count` / `warning_count` / `deprecation_count` and the per-request `logs` (message, level, `channel`, context; capped at 100). Use it to see what the code logged during exactly this request, which the global log files cannot pin to one request.

Other collectors present in the profile (router, security, twig, ...) are readable at the same URI shape; they return raw dumps rather than a curated shape.

## Failure paths

- `symfony-profiler-list` returns no profiles: the profiler is off (not enabled, or prod), or nothing has been requested since it was cleared. Fall back to log investigation.
- `symfony-profiler-get` on a stale/wrong token errors with "Profile ... not found". Re-list to get a live token; tokens change when the profiler storage is cleared.
- A collector URI returns `{"error": ...}` instead of data: that collector is not in this profile, or the name is wrong. Re-read `symfony-profiler://profile/{token}` for the real names.
- The failing request was a sub-request: the profile has a `parent_token` / `parent_profile`. The real exception often sits on the parent, not the fragment.
