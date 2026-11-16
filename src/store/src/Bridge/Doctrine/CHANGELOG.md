CHANGELOG
=========

1.0
---

 * Add the bridge
 * Add `Mirror\MirrorListener` and `Mirror\EntityRetriever`, mirroring entities with `#[Embedding]` on the class into any store and loading them for the hits
 * Add the `where`, `params` and `paramTypes` options to `OrmStore::query()` to filter the entities with a DQL condition
