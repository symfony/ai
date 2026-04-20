CHANGELOG
=========

0.8
---

 * [BC BREAK] `ClaudeCodeContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta` instead of raw strings

0.6
---

 * Add the bridge
