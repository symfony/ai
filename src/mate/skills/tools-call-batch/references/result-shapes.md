# Result shapes

What a call returns inside `result`. Paths in `{{ }}` and `pick` start below `untrusted_data`, which Mate skips for you. Values are examples.

## symfony-profiler-list

```
total: 32
truncated: true
profiles: [
  { token, ip, method, url, time (unix), time_formatted (ISO), status_code, parent_token, resource_uri }
]
```
Newest first. `profiles[0].token` is the latest match. `parent_token` is empty for a top-level request.

## symfony-profiler-get

```
token, ip, method, url, time, time_formatted, status_code, parent_token, resource_uri
collectors: [ { name, uri } ]
```

## symfony-profiler-summary

```
requests: 22
failed: 10
truncated: false
failing_requests_truncated: false
failing_requests_by_count: { "GET 500 /checkout": 3, ... }
```

## symfony-profiler-triage

```
token, method, url, status_code, resource_uri
db: { query_count, total_time_ms, duplicate_query_count, slowest_queries: [ { sql, count, total_time_ms, avg_time_ms } ], slowest_queries_truncated }
time: { duration_ms, init_time_ms }
exception: { has_exception, message?, class? }
logger: { error_count, warning_count, deprecation_count, scream_count }
```
A collector the profile does not have is missing.

## symfony-profiler-compare

```
collector, baseline, current   (token or tokens + run_count, then the summary fields)
delta: { field: number }
changed: { field: { baseline, current } }
verdict?: improved | unchanged | regressed | inconclusive
outliers?: { baseline|current: { field: { token: value } } }
note?
```

## Resource symfony-profiler://profile/<token>/<collector>

```
name
summary: { ... a short view of the collector ... }
data: { ... the full detail ... }
```
`exception`: `data.has_exception`, `message`, `class`, `file`, `line`, `trace: [ { file, line, ... } ]`.
`db`: `summary.query_count`, `data.queries: [ { sql, count, total_time_ms } ]`.
`time`: `summary.duration_ms`, `data.events: [ ... ]` slowest first.
`logger`: `summary` counts, `data.logs: [ { message, level, channel, context } ]`.
An unknown collector returns `{ "error": "..." }` and still counts as `ok: true`.

## monolog-search, monolog-context-search, monolog-tail

```
entries: [ { datetime, channel, level, message, context, extra, source_file, line_number } ]
total_matched
truncated
skipped_files
```
Parameters of `monolog-search`: `term`, `regex`, `level` (exact match: ERROR, CRITICAL, ...; an uncaught exception is CRITICAL), `channel`, `from`, `to` (any PHP date string, `-1 hour` counts back from now), `limit`.

## symfony-messenger-failed

```
container: { path, skipped }
transports: [
  { transport, storage, message_count, scanned, group_count, groups: [
      { group_id, count, message_classes, exception_class, failed_in, sample_messages, trace, retry_count, first_failed_at, last_failed_at, ids }
  ], undecodable: { count, messages } }
]
```
Pass `group` together with `transport` to list the messages of one group.
