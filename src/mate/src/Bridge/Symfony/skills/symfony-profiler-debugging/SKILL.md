---
name: symfony-profiler-debugging
description: Diagnose a Symfony request that failed, errored (5xx), or was slow, using the profiler. Use when a specific request misbehaved and the app has the profiler enabled (usually dev). Not for cross-request trends over time (use log investigation) or DI wiring bugs (use service inspection).
---

# Profiler debugging

Reads the profiler through Mate's CLI. Five tools, two resources:

- `symfony-profiler-triage` triages one request: query count, duplicate queries, the most expensive statements, total duration, whether an exception occurred, and the logger error/warning counts. `--url=/checkout` picks the newest profile for that path, `--token=<t>` an exact one. With neither it takes the most recent profile. Collectors the profile does not have are omitted, so a missing key means "not collected", not "zero". Start here: it answers the first question in one call, where listing, getting and reading a collector separately takes three rounds.
- `symfony-profiler-list` filters profiles (`method`, `url`, `ip`, `statusCode`, `context`, `from`, `to`, `limit`). Newest first, so `--limit=1` is the latest. Returns `total` and `truncated` first, then summaries with a `resource_uri` per profile. The default `limit` is 20: before aggregating over "all requests" (an average, a count, the slowest route), check `truncated`. When it is true, you saw only a sample (at most 500, also for `--limit=0`), so narrow the filters instead of listing everything.
- `symfony-profiler-summary` answers "how many requests failed and which fails most" in one call: `requests`, `failed` (status 400+) and `failing_requests_by_count` (`METHOD STATUS path`, most frequent first, at most 20 with `failing_requests_truncated`) over the latest `limit` (default 500) top-level requests. `truncated` says older ones were left out; `--limit=0` looks at all. Start here for "what fails" questions, then list the cases with `symfony-profiler-list`.
- `symfony-profiler-get --token=<t>` returns one profile's metadata and its `collectors`, each with a `uri`.
- `symfony-profiler-compare --baseline=<t1> --current=<t2> [--collector=db]` diffs the `summary` of one collector across two profiles. Either side accepts several comma-separated tokens (`--baseline=<t1>,<t2>,<t3>`), reduced to their median, when a single run is too noisy to trust.
- `symfony-profiler://profile/{token}` lists the collectors this profile actually has, each with its URI.
- `symfony-profiler://profile/{token}/{collector}` returns that collector, as `{name, data, summary}`. `summary` is the triage view, `data` the full detail.

These commands accept `--format`: `json` to parse the result, `toon` (when `helgesverre/toon` is installed) for the smallest context footprint. On a large profile, prefer one of these over the human-readable default.

## Investigation loop

For errors and wrong output, the reading order below stands. The exception or the request data is the answer.

For a slow request, one collector read is never enough. A single read shows *that* something is expensive, never *everything* that is. A slow page is usually an N+1 *and* a missing index *and* an uncached fragment.

For slow, many-query or memory-heavy requests, investigate in a loop. Do not answer from the first read.

1. Triage first: `vendor/bin/mate tools:call symfony-profiler-triage` for the latest request, `--url=/checkout` for a known path. The numbers it returns usually already name the subsystem to look at.
2. Only when triage is not enough, find the exact profile. Do not scroll all profiles.
   - Error: `vendor/bin/mate tools:call symfony-profiler-list --statusCode=500 --limit=5`
   - Known URL: `--url=/checkout`. Latest request: `--limit=1`.
3. `vendor/bin/mate resources:read symfony-profiler://profile/<token>` to see which collectors exist. Apps differ, only read what is present.
4. Read the collector the symptom points at (reading order below).
5. Take **every** number in it that is out of proportion and trace it to a named source: which
   statement, which event, which code path emitted it. One number, one source, named.
6. Account for the total. Subtract what you just explained: if any of the load is still
   unattributed, go back to step 4 (next collector, or next number in this one).

**Stop condition (slow path):** done means every source of the load is named, not that one was found.
"The N+1 in the product listing" is one source. If it accounts for 40 of 54 queries, 14 queries
are still unexplained and the investigation is not finished. Name those too. Same for time:
if the slow events you identified sum to 300 ms of an 800 ms request, keep going.

Stop early only when the remainder is genuinely proportionate, and say so explicitly.

## When the profile does not explain it

Continue in the logs instead of stopping at the profile boundary. Trigger cases: `has_exception` is false on a 5xx, `logger` reports errors or warnings the collectors do not otherwise explain, or the work that failed happened in a different request (queue worker, sub-request, earlier write). If the Monolog bridge is installed, carry the identifier over: search with `monolog-search` for the message from the logger collector, or follow a request id from the logger context with `monolog-context-search`.

The profiler sees exactly one request. The logs see every request, including the one that swallowed the exception. Follow the skill for log investigation from there and come back with what it names.

## Commands, workers, cron

The profiler is not HTTP-only. `--profile` is a standard Symfony option that
`FrameworkBundle`'s console `Application` registers on *every* command, and
`ConsoleProfilerListener` then writes an ordinary profile into the same profiler storage,
with `method: BATCH` instead of an HTTP method. So a command, a worker or a cron job is
debugged with exactly the tools above:

```
bin/console app:import --profile
vendor/bin/mate tools:call symfony-profiler-triage
```

Without `--url`/`--token`, triage takes the most recent profile, which is the command that
just ran. From there the reading order below applies unchanged.

## Reading order

Step 4 picks the collector. On the slow path, step 5 traces its numbers. Branch on the symptom.

**Errored (5xx / exception):** read `exception` first.
`data.class`, `message` (secrets scrubbed), `file`, `line`, and `trace` (top 10 frames) are the fix. If `has_exception` is false on an error response, the failure is upstream and this profile will not name it: check `request` for the status and `logger` for what was logged, then follow it into the logs (see "When the profile does not explain it"). A swallowed exception leaves a log line even when it leaves no collector entry.

**Slow but succeeded:** skip `exception`, go to `time`, then `db`, then `http_client`, then `memory`.
- `time`: `duration_ms` is total. `events` are sorted slowest first with a `category` (e.g. `doctrine`, `template`, `controller`) that tells you which subsystem ate the time. That category picks the next collector.
- `db`: `query_count` plus `summary.duplicate_query_count`. `queries` are grouped by identical SQL, sorted by `total_time_ms`. An N+1 shows as one grouped entry with a high `count` (same statement fired in a loop), or a high `duplicate_query_count`. One slow statement shows as a high `avg_time_ms`. `sample_params` shows what was bound. Grouped list caps at 50 (`queries_truncated`).
- `memory`: `usage_percent` against the limit. Only relevant when the request is heavy or OOMs.
- `http_client`: when `time` points at the app waiting rather than computing, this lists the outbound calls, slowest first: `client`, `method`, `url`, `http_code`, `total_time_ms`, `failed`, `error`, `redirect_count`. `summary` has `request_count`, `total_time_ms`, `failed_count` and the `slowest` call. A `failed` call has `http_code` 0 (timeout, DNS, refused), 400+, or an `error`. Headers and bodies are never shown, URL secrets are masked as `***`. Lists cap at 50 (`calls_truncated`).

**Wrong output / bad input:** read `request`.
`summary` has `method`, `path`, `route`, `status_code`, `content_type`. `data` has the sanitized bags (`request_query`, `request_request`, `request_headers`, `session_attributes`). Sensitive values, the raw body, and the curl command are redacted or omitted by design, so do not expect to read a password back.

**Tie a log line to the request:** `logger` has `error_count` / `warning_count` / `deprecation_count` and the per-request `logs` (message, level, `channel`, context; capped at 100). Use it to see what the code logged during exactly this request, which the global log files cannot pin to one request.

A non-zero `error_count` or `warning_count` with no matching exception is the handoff signal: this request logged something the collectors do not explain. Take the message or a context key from `logs` into `monolog-search` / `monolog-context-search` (see "When the profile does not explain it") to find out whether it is a one-off or has been happening all week.

Need more than `logger` captured: a wider time window, a channel outside this request, or entries from before it started? Fetch the profile and search the logs together in one call instead of two separate `tools:call` round-trips (see the `mate-tools-call-batch` skill):

```bash
vendor/bin/mate tools:call-batch --json='[{"tool": "symfony-profiler-get", "params": {"token": "<t>"}}, {"tool": "monolog-search", "params": {"level": "ERROR", "from": "-1 hour"}}]'
```

`from` counts back from now, not from the request, so choose a window that still reaches the request.

To start from the latest failed or slow request without knowing its token, chain the calls instead: copy a recipe from `references/recipes.md` of the `mate-tools-call-batch` skill (failed request with exception and logs, slowest part of a request, before and after a fix) and change only the filter values.

Other collectors present in the profile (router, security, twig, ...) are readable at the same URI shape; they return raw dumps rather than a curated shape.

## Prove the fix

A performance fix is not done when the page still renders. It is done when the number moved.
Keep the token you diagnosed on, then after the change reproduce the same request, take the new
token from `symfony-profiler-list --limit=1`, and compare:

```
mate tools:call symfony-profiler-compare --baseline=<old> --current=<new> --collector=db
```

It returns both summaries, a `delta` per shared numeric metric, a `changed` entry (`{baseline,
current}`) for a shared field that changed but isn't numeric, such as `exception`'s
`has_exception`, and a `verdict` (`improved`, `unchanged`, `regressed`) built from one leading
metric per collector. Report the delta or the changed pair, not the impression. `--collector=time`
or `memory` works the same way for whatever metric the symptom was about. A collector with no
field anyone can call unambiguously better when lower (`mailer`, `request`, `router`, `http_client`, `config`) has no `verdict`,
so read `delta`/`changed` directly there. For `time` and `memory`, a change under 5 percent of the
baseline counts as `unchanged`. For the rest, `unchanged` after a fix means the fix did not hit the
hot path. Go back to the reading order.

A single request can be noisy (cache warmup, GC, an unrelated slow query on that one run). If
the delta looks marginal, reproduce a few times on each side and pass every token, comma-separated,
to `--baseline`/`--current`: `symfony-profiler-compare --baseline=<t1>,<t2>,<t3> --current=<t4>,<t5>,<t6>`.
Each side's numeric fields are then reduced to their median before comparing, and the response reports `run_count`
plus every token used instead of a single `token`. The median ignores one odd run. A run further than 50 percent
from its side's median is listed under `outliers` with the value of every run, and the `verdict` for that metric
becomes `inconclusive`. Do not claim a gain then. Read the listed profiles with `symfony-profiler-get`, find out
why that run differs (cold cache, initialization), and measure again.

## Failure paths

- `symfony-profiler-list` returns no profiles: the profiler is off (not enabled, or prod), or nothing has been requested since it was cleared. Fall back to log investigation.
- `symfony-profiler-get` on a stale/wrong token errors with "Profile ... not found". Re-list to get a live token; tokens change when the profiler storage is cleared.
- A collector URI returns `{"error": ...}` instead of data: that collector is not in this profile, or the name is wrong. Re-read `symfony-profiler://profile/{token}` for the real names.
- The failing request was a sub-request: the profile has a `parent_token` / `parent_profile`. The real exception often sits on the parent, not the fragment.
