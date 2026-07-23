Knowledge Bridge
================

Provides structured, offline access to official documentation as crawlable MCP
tools for Symfony AI Mate. Agents browse the table of contents, read specific
pages, and run substring search across cached content instead of guessing from
training data.

This bridge does **not** perform semantic / vector / RAG search. A
`SearcherInterface` seam is provided so a future implementation can plug in
embedding-based search without changing the tool surface.

Other extensions can register additional providers by tagging a service with
`ai_mate.knowledge_provider`. The Symfony bridge ships a `SymfonyDocsProvider`
that becomes available when this package is installed alongside it.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
