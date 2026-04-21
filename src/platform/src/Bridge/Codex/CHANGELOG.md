CHANGELOG
=========

0.9
---

 * [BC BREAK] Align `ModelCatalog` with the official OpenAI Codex models list (https://developers.openai.com/codex/models): add `gpt-5.2` and remove `gpt-5.2-codex`, `gpt-5.1-codex`, `gpt-5-codex`, `gpt-5-codex-mini`

0.8
---

 * [BC BREAK] `CodexContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Add the bridge
