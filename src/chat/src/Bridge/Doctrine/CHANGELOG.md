CHANGELOG
=========

0.8
---

 * [BC BREAK] `DoctrineDbalMessageStore::save()` now upserts a single row instead of inserting a new row on every call; the table schema is unchanged but `added_at` is refreshed on every save

0.1
---

 * Add the bridge
