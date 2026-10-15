CHANGELOG
=========

0.15
----

 * Add non-streaming tool calling support for chat completion

0.11
----

 * Add a `baseUrl` argument to the model client and the factory to target a custom HuggingFace inference router

0.8
---

 * [BC BREAK] `HuggingFaceContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * [BC Break] Change all Output class properties from public to private readonly with getter methods

0.7
---

 * Add text-ranking task support for cross-encoder reranking models

0.1
---

 * Add the bridge
