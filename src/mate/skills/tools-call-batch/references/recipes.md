# Recipes

Copy one, change the values in `params` and `pick`. Run it with `tools:call-batch --json='<the array>'`.
Placeholders like `<status>` are yours to fill in.

## Latest failed request, with its exception and the logs

Question: "what broke in the last 500?" The exception message goes straight into the log search, so you find the log lines of this very error.

```json
[
  {"id": "list", "tool": "symfony-profiler-list", "params": {"statusCode": 500, "limit": 1}, "output": false},
  {"id": "p", "tool": "symfony-profiler-get", "params": {"token": "{{list.profiles[0].token}}"}, "pick": ["token", "url", "time_formatted", "status_code"]},
  {"id": "ex", "resource": "symfony-profiler://profile/{{list.profiles[0].token}}/exception", "pick": ["data.class", "data.message", "data.file", "data.line", "data.trace[:5]"]},
  {"tool": "monolog-search", "params": {"term": "{{ex.data.message}}", "limit": 5}, "pick": ["entries[*].datetime", "entries[*].level", "entries[*].message", "total_matched"]}
]
```

Change `statusCode` (404, 403, ...) or add `"url": "/checkout"` to the list call for one route. Do not add a `level` to the log search: it matches exactly, and an uncaught exception is logged as `CRITICAL`, not `ERROR`. If the profile has no exception (`data.message` is listed under `missing_paths`), the log search fails: read the `logger` collector of that profile instead.

## Slowest part of a request

Question: "why was this request slow?" Use the token of a request you know, or the latest one.

```json
[
  {"id": "list", "tool": "symfony-profiler-list", "params": {"url": "<path>", "limit": 1}, "output": false},
  {"tool": "symfony-profiler-triage", "params": {"token": "{{list.profiles[0].token}}"}},
  {"resource": "symfony-profiler://profile/{{list.profiles[0].token}}/db", "pick": ["summary", "data.queries[:5]"]},
  {"resource": "symfony-profiler://profile/{{list.profiles[0].token}}/time", "pick": ["summary", "data.events[:8]"]}
]
```

`symfony-profiler-triage` already names query count, duplicates and duration. Read the collectors only for the number it points at.

## Which requests fail most, with examples

```json
[
  {"tool": "symfony-profiler-summary", "params": {"limit": 200}},
  {"tool": "symfony-profiler-list", "params": {"statusCode": 500, "limit": 3}, "pick": ["total", "profiles[*].url", "profiles[*].token"]}
]
```

## Several collectors of one known profile

```json
[
  {"resource": "symfony-profiler://profile/<token>/db", "pick": ["summary"]},
  {"resource": "symfony-profiler://profile/<token>/time", "pick": ["summary"]},
  {"resource": "symfony-profiler://profile/<token>/logger", "pick": ["summary", "data.logs[:5]"]},
  {"resource": "symfony-profiler://profile/<token>/exception", "pick": ["data.message", "data.class"]}
]
```

## Compare before and after a fix

Request the old and the new page three times each. Then compare in one call: the lists collect the tokens, the compares read them.

```json
[
  {"id": "old", "tool": "symfony-profiler-list", "params": {"url": "<old path>", "limit": 3}, "output": false},
  {"id": "new", "tool": "symfony-profiler-list", "params": {"url": "<new path>", "limit": 3}, "output": false},
  {"tool": "symfony-profiler-compare", "params": {"baseline": "{{old.profiles[0].token}},{{old.profiles[1].token}},{{old.profiles[2].token}}", "current": "{{new.profiles[0].token}},{{new.profiles[1].token}},{{new.profiles[2].token}}", "collector": "db"}},
  {"tool": "symfony-profiler-compare", "params": {"baseline": "{{old.profiles[0].token}},{{old.profiles[1].token}},{{old.profiles[2].token}}", "current": "{{new.profiles[0].token}},{{new.profiles[1].token}},{{new.profiles[2].token}}", "collector": "time"}}
]
```

The list also contains sub-requests, such as the error page of a failed request. They have a `parent_token`. If the pages you compare can fail or redirect, list more than three and take only entries whose `parent_token` is empty.

For the same URL before and after a code change, take the tokens of the first three runs from `symfony-profiler-list` and write them down, then list again after the change. The list shows the newest first, so the old runs are no longer at the top. A verdict of `inconclusive` means one run stands out. Look at the runs listed under `outliers` before you claim a gain.

## Failed Messenger messages and what the worker logged

```json
[
  {"tool": "symfony-messenger-failed", "pick": ["transports[0].group_count", "transports[0].groups[:3]"]},
  {"tool": "monolog-search", "params": {"channel": "messenger", "level": "CRITICAL", "limit": 5}, "pick": ["entries[*].message"]}
]
```

## When nothing matches

If the first list is empty, the calls that need its token fail with "profiles is empty". That is the answer: there is no such request in the profiler (profiler off, storage cleared, or nothing requested). Say so, then continue in the logs.
