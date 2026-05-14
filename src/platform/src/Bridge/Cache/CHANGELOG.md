CHANGELOG
=========

0.9
---

 * Hash `MessageBag` inputs by content rather than by per-instance UUID, so two bags carrying the same conversation hit the same cache entry
 * Add `CachePlatform::lookup()` to retrieve a cached result without invoking the underlying platform on a miss

0.3
---

 * Add the bridge
