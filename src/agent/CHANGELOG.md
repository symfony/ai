CHANGELOG
=========

0.7
---

 * [BC BREAK] Change `SkillMetadataInterface::getCompatibility()` return type from `?array` to `?string`
 * [BC BREAK] Rename `SkillMetadataInterface::getFrontMatters()` to `getFrontmatter()`
 * Fix missing path separator in `SkillParser` reference and asset loaders
 * Fix typo in `SkillValidator::ALLOWED_FIELDS` (`licence` → `license`)
 * Fix `SkillValidator` description max length from `1064` to `1024` (per spec)
 * Add skill evaluation system with `EvalSuiteLoader`, `EvalRunner`, `LlmGrader`, `WorkspaceManager`, and `BenchmarkAggregator`
 * Add `GithubSkillLoader` for loading skills from GitHub repositories via the Contents API
 * Add `ChainSkillLoader` for composing multiple skill loaders transparently
 * Add `SkillParserInterface::parseFromContent()` and `parseMetadataFromContent()` for source-agnostic skill parsing

0.4
---

 * [BC BREAK] Rename `Symfony\AI\Agent\Toolbox\Tool\Agent` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`
 * [BC BREAK] Change AgentProcessor `keepToolMessages` to `excludeToolMessages` and default behaviour to preserve tool messages
 * Add `MetaDataAwareTrait` to `MockResponse`, the metadata will also be set on the returned `TextResult` when calling the `toResult` function
 * Add `HasSourcesTrait` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`

0.3
---

 * [BC BREAK] Drop toolboxes `StreamResult` in favor of `StreamListener` on top of Platform's `StreamResult`
 * [BC BREAK] Rename `SourceMap` to `SourceCollection`, its methods from `getSources()` to `all()` and `addSource()` to `add()`
 * [BC BREAK] Third Argument of `ToolResult::__construct()` now expects `SourceCollection` instead of `array<int, Source>`
 * Add `maxToolCalls` parameter to `AgentProcessor` to limit tool calling iterations and prevent infinite loops
 * Add `Countable` and `IteratorAggregate` implementations to `SourceCollection`

0.2
---

 * [BC BREAK] Switch `MemoryInputProcessor` to use `iterable` instead of variadic arguments

0.1
---

 * Add the component
