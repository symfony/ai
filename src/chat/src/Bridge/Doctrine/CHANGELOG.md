CHANGELOG
=========

0.8
---

 * [BC BREAK] `DoctrineDbalMessageStore::save()` now upserts a single row instead of inserting a new row on every call; the table schema changed from `(id, messages, added_at)` to `(messages, updated_at)`

0.1
---

 * Add the bridge
