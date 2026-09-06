---
name: tools-call-batch
description: Run several Mate tools as one command call instead of one `tools:call` per tool. Reach for it when an investigation genuinely needs results from multiple tools together (e.g. correlating a profiler request with logs from the same time window), since each `tools:call` round-trip has a roughly fixed cost regardless of response size. Not for a single tool call, and not for tools that write or change state.
---

# tools:call-batch

`tools:call-batch --json='[...]'` runs several tools in one command call. Each round-trip an
agent makes back to Mate costs roughly the same regardless of how much a single tool response
shrinks, because the whole conversation is resent every turn. When one investigation genuinely
needs multiple tool results, one batched call replaces N individual `tools:call` invocations and
saves the (N-1) extra round-trips.

## When to reach for it

Use it when the results of two or more tools answer **one** question together, not when you
happen to be calling tools in sequence for unrelated reasons.

- Correlating a slow request with logs from the same time window: fetch the profiler profile and
  search the logs for that window in one call.
- Running a combined lint pass across independent read-only checks.

Do not reach for it for a single tool call (`tools:call` is simpler) or when later calls in the
sequence depend on the result of an earlier one (e.g. reading a profiler token you don't have yet)
— those cannot be expressed as one JSON array up front and must stay separate `tools:call` calls.

## Usage

```bash
bin/mate tools:call-batch --json='[
  {"tool": "symfony-profiler-get", "params": {"token": "abc123"}},
  {"tool": "monolog-search", "params": {"level": "ERROR", "from": "2026-01-01T12:00:00"}}
]'
```

Each entry is `{"tool": "<name>", "params": {...}}` (`params` is optional for a tool that takes
none). The result is a JSON array, one entry per call, in the same order:
`{"tool": "...", "ok": true|false, "result": <decoded tool output>|null, "error": "..."|null}`.

Supports `--format=json` (default is a human-readable `pretty` rendering, one section per tool)
and `--format=toon` when `helgesverre/toon` is installed, same as `tools:call`.

## What it does not do

- **No concurrency.** Calls run sequentially, in array order. The saving is the collapsed number
  of agent-visible round-trips, not wall-clock parallelism; do not expect it to run faster than
  the sum of the individual tool calls.
- **No partial abort.** One call throwing or targeting an unknown tool name does not stop the
  rest of the batch; every entry gets its own `ok`/`result`/`error`, so read the whole array.
- **Rejects tools that look mutating.** A tool whose name contains `-apply`, `-fix`, `-install`,
  `-enable`, `-disable`, `-override`, `-reset` or `-prune` is excluded (reported as a failed entry,
  not a batch-wide error) because there is no metadata yet distinguishing a read-only tool from
  one that writes, and running such a tool alongside others in the same call is a state-mutation
  footgun even without real concurrency. Call a mutating tool on its own with `tools:call`.
