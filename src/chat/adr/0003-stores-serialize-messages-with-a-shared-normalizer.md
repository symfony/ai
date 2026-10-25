---
status: accepted
date: 2026-10-25
---

# 0003. Stores serialize messages with one shared `MessageNormalizer`

## Context and Problem Statement

Backends such as databases, Redis or search engines store text, not PHP objects. A
conversation contains system, user, assistant and tool call messages with multimodal
content, tool calls, thinking blocks with signatures, metadata and message ids. If every
bridge serialized messages its own way, the formats would drift and a round trip could
lose data the next model request depends on, such as a thinking signature.

## Decision

`MessageNormalizer` (`src/MessageNormalizer.php`) is a Symfony Serializer normalizer and
denormalizer for `MessageInterface` that the text-based bridges share. The Cloudflare,
Doctrine, Meilisearch, MongoDb, Pogocache, Redis and SurrealDb stores build a `Serializer`
with it (plus `ArrayDenormalizer` and, where needed, `ToolCallNormalizer`) as the default
of an injectable `SerializerInterface`.

- Each message is stored with its id (the key is configurable through the `identifier`
  context), its class as `type`, `content`, base64 data URLs for binary content parts in
  `contentAsBase64`, `toolsCalls`, `metadata` and an `addedAt` timestamp.
- Assistant messages are stored with an ordered `parts` list of `Text`, `Thinking` (with
  signature) and `ToolCall` blocks, so their sequence survives the round trip. Payloads
  without `parts` still denormalize, without guaranteed ordering.
- Denormalization restores the original message id with `withId()` and the metadata.
- Unknown message or content types throw `Exception\LogicException`.

Stores whose backend serializes PHP values itself, the Cache and Session bridges and
`InMemory\Store`, save the `MessageBag` object directly.

## Consequences

- **Good:** one format for all text-based backends; a fix in the normalizer applies to
  all of them.
- **Good:** message ids, metadata and thinking signatures survive persistence, so a
  restored conversation can be sent to the model again.
- **Trade-off:** the stored format uses PHP class names as `type`, so renaming a message
  or content class affects data already stored.
- **Trade-off:** binary content is inlined as data URLs, which makes stored
  conversations with files large.

## Alternatives Considered

- **Per-bridge serialization**: not chosen because the formats would diverge and every
  bridge would have to handle every content type.
- **PHP `serialize()` for all backends**: used only where the backend does it natively;
  JSON keeps the data readable and queryable by search backends.

## References

- `src/MessageNormalizer.php`: the normalizer and the `parts` format.
- `src/Bridge/Redis/MessageStore.php`, `src/Bridge/Doctrine/DoctrineDbalMessageStore.php`:
  bridges using it.
- `src/Bridge/Cache/MessageStore.php`, `src/Bridge/Session/MessageStore.php`: bridges
  storing the object.
- `CHANGELOG.md`: the 0.9 entry introducing `parts`.
