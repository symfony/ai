CHANGELOG
=========

0.3
---

 * Add `StreamListenerInterface` to hook into response streams
 * [BC BREAK] Change `TokenUsageAggregation::__construct()` from variadic to array
 * Add `TokenUsageAggregation::add()` method to add more token usages
 * [BC BREAK] `CachedPlatform` has been renamed `CachePlatform` and moved as a bridge, please require `symfony/ai-cache-platform` and use `Symfony\AI\Platform\Bridge\Cache\CachePlatform`
 * [BC BREAK] `Metadata::merge()` method signature has changed to accept `Metadata` instead of array
 * [BC BREAK] Behavior of `Metadata::add()` has changed to merge existing keys instead of overwriting them

0.2
---

 * [BC BREAK] Change `ChoiceResult::__construct()` from variadic to accept array of `ResultInterface`

0.1
---

 * Add the component
