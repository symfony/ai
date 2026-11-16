CHANGELOG
=========

1.0
---

 * [BC BREAK] Move the bridge to the Agent component as `Symfony\AI\Agent\Bridge\Session`, implementing its message store interface and keeping each conversation by its ID
 * [BC BREAK] Remove `setup()` and `drop()`, use `delete()` to remove a single conversation

0.1
---

 * Add the bridge
