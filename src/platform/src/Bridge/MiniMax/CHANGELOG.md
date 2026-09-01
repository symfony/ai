CHANGELOG
=========

0.14
----

 * [BC BREAK] Stop polling asynchronous tasks inside `MiniMaxResultConverter`. Video generation and asynchronous speech synthesis now return a `Result\JobResult` carrying a serializable job handle, resolved through the new `MiniMaxJobClient` — see the platform `UPGRADE` notes. `MiniMaxResultConverter` no longer takes an HTTP client, API key, endpoint or clock

0.11
----

 * Add the bridge
