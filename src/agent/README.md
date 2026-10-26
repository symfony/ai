# Symfony AI - Agent Component

The Agent component provides a framework for building AI agents that, sits on top of the Platform and Store components,
allowing you to create agents that can interact with users, perform tasks, and manage workflows.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-agent
```

## Tool Bridges

To use a specific tool, install the corresponding bridge package:

| Tool              | Package                             |
|-------------------|-------------------------------------|
| Brave Search      | `symfony/ai-brave-tool`             |
| Clock             | `symfony/ai-clock-tool`             |
| Filesystem        | `symfony/ai-filesystem-tool`        |
| Firecrawl         | `symfony/ai-firecrawl-tool`         |
| Mapbox            | `symfony/ai-mapbox-tool`            |
| MCP               | `symfony/ai-mcp-tool`               |
| Ollama Web Search | `symfony/ai-ollama-tool`            |
| OpenMeteo         | `symfony/ai-open-meteo-tool`        |
| SerpApi           | `symfony/ai-serp-api-tool`          |
| SimilaritySearch  | `symfony/ai-similarity-search-tool` |
| Tavily            | `symfony/ai-tavily-tool`            |
| Web Scraper       | `symfony/ai-scraper-tool`           |
| Wikipedia         | `symfony/ai-wikipedia-tool`         |
| YouTube           | `symfony/ai-youtube-tool`           |

## Message Store Bridges

To continue and save conversations in a specific message store, install the corresponding bridge package:

| Message Store | Package                                |
|---------------|----------------------------------------|
| Cache         | `symfony/ai-cache-message-store`       |
| Cloudflare    | `symfony/ai-cloudflare-message-store`  |
| Doctrine      | `symfony/ai-doctrine-message-store`    |
| Meilisearch   | `symfony/ai-meilisearch-message-store` |
| MongoDB       | `symfony/ai-mongo-db-message-store`    |
| Pogocache     | `symfony/ai-pogocache-message-store`   |
| Redis         | `symfony/ai-redis-message-store`       |
| Session       | `symfony/ai-session-message-store`     |
| SurrealDB     | `symfony/ai-surreal-db-message-store`  |

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/agent.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
