---
name: symfony-request-triage
description: First step ONLY when a Symfony request misbehaved and you do not yet know whether the answer is in the profiler, the logs, or the container. One call, then it hands off to the right skill. Skip it entirely and go straight to that skill if you already know the profile token, the log channel or query, or that it is a DI wiring bug.
---

# Request triage

One decision: where to start. Make it with one call, then hand off.

1. Is it a wiring symptom, not a runtime one? "Service not found", wrong implementation injected, a tag/listener that never fires, a decorator that does nothing. These never reach a request cleanly. Go straight to service inspection.

2. Otherwise probe for a profile: `mate tools:call symfony-profiler-list --limit=1` (or `--url=<path>` / `--statusCode=500` if you know them).
   - A profile matching the request exists: use profiler debugging. It pins one request to its exception, queries, and timing, which the logs cannot.
   - No profiles (profiler off, or prod): use log investigation. Also the right tool when the question is about time or frequency ("when did this start", "how often"), not one request.

3. Not sure it is even one request, or it spans many (a user's whole session, a recurring error): use log investigation and pivot on a context field.

The profiler is the sharper instrument but exists only where it is enabled and only until storage is cleared. Logs are always there but cannot isolate a single request the way a profile can. When both exist, start with the profile and drop to logs for history.
