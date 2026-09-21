CHANGELOG
=========

0.14
----

 * [BC BREAK] Stop polling predictions inside `Client`. An invocation now returns a `Result\JobResult` carrying a serializable job handle, resolved through the new `ReplicateJobClient`, built by `Factory::createJobClient()` — see the platform `UPGRADE` notes. `Client` no longer takes a clock and gained `get()` for the prediction lookup

0.11
----

 * Add a `baseUrl` argument to the client and the factory to target Replicate-compatible endpoints

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
