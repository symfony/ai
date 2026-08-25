---
name: symfony-profiler-debugging
description: >-
  Diagnose a slow, failing, or misbehaving Symfony HTTP request by orchestrating
  Mate's Symfony profiler tools. Use when the user reports a 500 error, a slow
  endpoint, an N+1 query problem, an unexpected response, or asks "what happened
  on the last request". Walks from locating the right profile to reading only the
  collector that explains the symptom.
---

# Debugging a Symfony request with the profiler

This skill turns Mate's Symfony profiler capabilities into a repeatable triage
workflow. It assumes the `symfony/http-kernel` profiler is enabled (dev/test) and
that the project has been served with `bin/mate serve`.

## Capabilities you will use

| Capability | Type | Purpose |
|---|---|---|
| `symfony-profiler-list` | tool | Find profiles by method, URL, IP, status code, date range, or context. |
| `symfony-profiler-get` | tool | Resolve one token to its metadata and the list of available collectors. |
| `symfony-profiler://profile/{token}` | resource | Triage view: request metadata + which collectors exist. |
| `symfony-profiler://profile/{token}/{collector}` | resource | Detail view: the data for a single collector. |
| `symfony-services` | tool | Container introspection, for dependency-injection / "service not found" cases. |

## Workflow

Follow the **triage → detail** pattern. Do not read every collector — read the one
that matches the symptom.

### 1. Locate the profile

- "the last request" → `symfony-profiler-list` with `limit=1`.
- A specific failure → filter: `symfony-profiler-list` with `statusCode=500`, or
  `url=/checkout`, or `method=POST`, narrowing with `from`/`to` when the user gives
  a time window.

Each result carries a `resource_uri`. Prefer it over guessing token strings.

### 2. Triage

Read `symfony-profiler://profile/{token}` (or call `symfony-profiler-get`). This is
the cheap overview: HTTP method, URL, status code, total time, and the list of
collectors that actually have data for this request. Use it to decide *which*
collector to open next — not to dump everything.

### 3. Read only the collector that explains the symptom

Map the symptom to a collector, then read
`symfony-profiler://profile/{token}/{collector}`:

| Symptom | Collector | What to look for |
|---|---|---|
| 500 / uncaught error | `exception` | Exception class, message, the failing frame. |
| Slow response | `time` | Total + per-event durations; find the dominant span. |
| Slow response with a DB cause | `db` | Query count and duration; repeated near-identical queries = N+1. |
| Emails not sent / wrong recipient | `mailer` | Queued vs. sent messages, envelope. |
| Missing / wrong translations | `translation` | Defined, missing, and fallback messages. |
| Unexpected output or routing | `request` | Controller, route, request/response attributes. |
| Memory pressure | `memory` | Peak usage for the request. |
| Something logged during the request | `logger` | Log entries captured for this request. |

See [references/collectors.md](references/collectors.md) for the per-collector
field guide and how to read each one.

### 4. Service / dependency-injection problems

If the exception is a `ServiceNotFoundException`, an autowiring failure, or the
user asks "is service X registered / what is it aliased to", switch to
`symfony-services` and filter by the service id or class instead of the profiler.

### 5. Correlate with logs (optional)

When the profiler shows an error but not the root cause, and the Monolog bridge is
installed, cross-reference with `monolog-log-search` around the request's
timestamp.

## Rules

- **One collector at a time.** The triage view tells you which one is worth the
  context. Reading all of them defeats the purpose.
- **Trust the redaction.** Cookies, session data, auth headers, and sensitive env
  vars are already redacted by the bridge — do not try to route around that to
  recover secrets.
- **Report the diagnosis, not the dump.** End with the specific cause (e.g. "N+1:
  42 identical `SELECT … FROM product` queries from the cart renderer") and the
  fix, not a paste of the collector payload.
