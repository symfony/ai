---
status: accepted
date: 2026-10-25
---

# 0003. Stores serialize messages with one shared `MessageNormalizer`

## Context and Problem Statement

Backends such as databases, Redis or search engines store JSON or documents, not PHP
objects. A conversation has system, user, assistant and tool call messages with
multimodal content, tool calls, thinking blocks with signatures, metadata and message
ids. If every bridge serialized messages its own way, the formats would drift, and a
round trip could lose data the next model request needs, such as a thinking signature.

## Decision

`MessageNormalizer` (`src/MessageNormalizer.php`) is a Symfony Serializer normalizer and
denormalizer for `MessageInterface`. The bridges whose backend does not store PHP
objects share it: the Cloudflare, Doctrine, Meilisearch, MongoDb, Pogocache, Redis and
SurrealDb stores build a `Serializer` with it and the Platform's `ToolCallNormalizer` as
the default of an injectable `SerializerInterface`. They either encode the result as
JSON or pass the arrays to the backend as documents.

- Each message is stored with its id (the key can be set with the `identifier` context),
  its class as `type`, `content`, base64 data URLs for binary content in
  `contentAsBase64`, `toolsCalls`, `metadata` and an `addedAt` timestamp.
- Assistant messages are stored with an ordered `parts` list of `Text`, `Thinking` (with
  signature) and `ToolCall` blocks, so their order survives the round trip. Older data
  without `parts` still loads, but without guaranteed order.
- Loading restores the original message id with `withId()` and the metadata.
- Unknown message or content types throw `Exception\LogicException`.

Stores whose backend serializes PHP values itself (the Cache and Session bridges and
`InMemory\Store`) save the `MessageBag` object directly.

## Consequences

- **Good:** one format for all these backends; a fix in the normalizer helps all of
  them.
- **Good:** message ids, metadata and thinking signatures survive storage, so a loaded
  conversation can be sent to the model again.
- **Trade-off:** the stored format uses PHP class names as `type`, so renaming a message
  or content class affects data already stored.
- **Trade-off:** binary content is stored inline as data URLs, which makes stored
  conversations with files large.

## Alternatives Considered

- **Per-bridge serialization**: the early Meilisearch store (#239) had its own mapping.
  The Redis store (#797) introduced the shared normalizer, meant to be reused by the
  other stores.
- **Assistant messages as `content` plus `toolsCalls`**: the older format, replaced by
  `parts` after #1853 reworked `AssistantMessage` to keep the order of its blocks and
  their provider-specific data.

## History

- 2025-11-07, #797 by @Guikingone (Guillaume Loulier): added `MessageNormalizer` together with the Redis store.
- 2025-11-22, #907 by @Guikingone (Guillaume Loulier): MongoDB store; the normalizer gets the configurable `identifier` key.
- 2025-12-13, #1001 by @Guikingone (Guillaume Loulier): better handling of message ids (UUIDs) in the normalizer.
- 2026-04-06, #1880 by @chr-hertel (Christopher Hertel): tool calls are serialized by the Platform's `ToolCallNormalizer` instead of `JsonSerializable`.
- 2026-05-09, #1853 by @valtzu: reworked `AssistantMessage` into ordered parts; the normalizer stores them as `parts`.
- 2026-09-23, #2533 by @RomainLvr: a tool call's signature survives the round trip.

## References

- `src/MessageNormalizer.php`: the normalizer and the `parts` format.
- `src/Bridge/Redis/MessageStore.php`, `src/Bridge/Doctrine/DoctrineDbalMessageStore.php`:
  bridges using it.
- `src/Bridge/Cache/MessageStore.php`, `src/Bridge/Session/MessageStore.php`: bridges
  storing the object.
- `CHANGELOG.md`: the 0.9 entry introducing `parts`.
