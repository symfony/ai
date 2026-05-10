CHANGELOG
=========

0.9
---

 * Add bridge with `knowledge-toc`, `knowledge-read`, and `knowledge-search` MCP tools
 * Add `DocsProviderInterface` extension point — services tagged `ai_mate.knowledge_provider` are exposed as crawlable documentation sources
 * Reuse `Symfony\AI\Store\Document\Loader\RstLoader` for RST section-based chunking
 * Auto-refresh the cached source repository and rebuilt index after `ai_mate_knowledge.cache_ttl_seconds` (default 24h)
