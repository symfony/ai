# Symfony AI - Chat Component

The Chat component provides a bridge for building chats with agents, sits on top of the Agent component,
allowing you to create chats and submit messages to agents.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-chat
```

## Message Store Bridges

To use a specific message store, install the corresponding bridge package:

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

- [Documentation](https://symfony.com/doc/current/ai/components/chat.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
