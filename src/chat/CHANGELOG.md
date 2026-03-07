CHANGELOG
=========

0.7
---

* Add `Chat::branch` for conversation branching with optional agent switching
* Add support for "multi-session" conversations via scoped identifiers
* Add `ManagedStoreInterface::drop(?string $identifier)` for conversation-scoped cleanup
* Add `MessageNormalizer` security hardening with content type whitelist

0.4
---

 * Add `ResetInterface` support to in-memory store

0.1
---

 * Add the component
 * Add `metadata` from `TextResult` to `AssistantMessage`
