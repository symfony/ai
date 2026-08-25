# Profiler collector field guide

Reference for step 3 of the [debugging workflow](../SKILL.md). Each section covers
one collector reachable at `symfony-profiler://profile/{token}/{collector}`. Only
collectors with data for a given request appear in that request's triage view, so
confirm availability there first.

## `exception`

The first stop for any 5xx. Look at:

- the exception **class** and **message** — usually names the failure directly;
- the **trace head** — the frame inside application code (skip vendor frames) is
  where to look in the source;
- a `previous` exception, if present — the real cause is often the innermost one.

## `time`

For "this is slow". The collector reports the total request duration and a set of
named events with durations.

- Find the **single dominant span** before optimizing anything — most requests are
  slow because of one thing, not many.
- A large gap between total time and the sum of named events points at work that
  isn't instrumented (often I/O or an external call).

## `db`

For slow requests with a database cause, when Doctrine is present.

- **Query count** is the headline. A page issuing hundreds of queries is almost
  always an **N+1**: many near-identical statements differing only by a bound id.
- High duration on a single query points at a missing index or an unbounded result
  set instead.
- Report the offending query shape and the call site, not every row.

## `request`

For "wrong output" / routing questions.

- `controller` and `route` confirm the request reached the handler you expect.
- request/response attributes and headers explain content negotiation and
  redirects.
- Payload values may be redacted — diagnose from shape and keys, not secrets.

## `mailer`

- Distinguish **queued** from **sent** messages.
- Check the **envelope** (from/to) when delivery goes to the wrong place.

## `translation`

- `missing` and `fallback` buckets explain untranslated or wrong-locale strings.
- The defined-message count confirms the catalogue loaded at all.

## `logger`

- Log entries captured during this request, in order.
- Useful to confirm whether an error was caught-and-logged versus thrown; for
  broader history use the Monolog bridge's `monolog-log-search` instead.

## `memory`

- Peak memory for the request. Relevant for OOM and for batch/export endpoints;
  rarely the first thing to read for a normal page.
