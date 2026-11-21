---
name: tools-call-batch
description: Run several Mate tools and resource reads as one command call instead of one `tools:call` or `resources:read` per item. Reach for it when an investigation genuinely needs results from multiple tools together (e.g. correlating a profiler request with logs from the same time window), since each `tools:call` round-trip has a roughly fixed cost regardless of response size. Not for a single tool call, and not for tools that write or change state.
---

# tools:call-batch

`tools:call-batch --json='[...]'` runs several tools and resource reads in one command call. Each round-trip an
agent makes back to Mate costs roughly the same regardless of how much a single tool response
shrinks, because the whole conversation is resent every turn. When one investigation genuinely
needs multiple tool results, one batched call replaces N individual `tools:call` invocations and
saves the (N-1) extra round-trips.

## When to reach for it

Use it when the results of two or more tools answer **one** question together, not when you
happen to be calling tools in sequence for unrelated reasons.

- Correlating a slow request with logs from the same time window: fetch the profiler profile and
  search the logs for that window in one call.
- Reading several collectors of one profile (`symfony-profiler://profile/<t>/db`, `/time`, `/logger`) in one call.

Do not reach for it for a single tool call (`tools:call` is simpler) or when later calls in the
sequence depend on the result of an earlier one (e.g. reading a profiler token you don't have yet):
those cannot be expressed as one JSON array up front and must stay separate `tools:call` calls.

## Usage

```bash
bin/mate tools:call-batch --json='[
  {"tool": "symfony-profiler-get", "params": {"token": "abc123"}},
  {"tool": "monolog-search", "params": {"level": "ERROR", "from": "2026-01-01T12:00:00"}}
]'
```

Each entry is `{"tool": "<name>", "params": {...}}` (`params` is optional for a tool that takes
none) or `{"resource": "<uri>"}` for a resource read. The result is a JSON array, one entry per call, in the same order:
`{"tool": "...", "ok": true|false, "result": <decoded tool output>|null, "error": "..."|null}`.
A resource read has `resource` (the URI) in place of `tool`.

Supports `--format=json` (default is a human-readable `pretty` rendering, one section per tool)
and `--format=toon` when `helgesverre/toon` is installed, same as `tools:call`.

## What it does not do

- **No concurrency.** Calls run sequentially, in array order. The saving is the collapsed number
  of agent-visible round-trips, not wall-clock parallelism; do not expect it to run faster than
  the sum of the individual tool calls.
- **No partial abort.** One call throwing or targeting an unknown tool name does not stop the
  rest of the batch; every entry gets its own `ok`/`result`/`error`, so read the whole array.
- **Runs tools as given.** There is no read-only check. Batch only tools you would also call
  one by one.
- **Exit code.** The command exits non-zero when any call failed. Call objects accept only the
  keys `tool`, `params` and `resource`. A call has exactly one of `tool` and `resource`, and a
  resource read takes no `params`.
