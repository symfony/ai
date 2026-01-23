## Symfony Bridge

Use MCP tools instead of CLI for container introspection:

| Instead of...                  | Use                 |
|--------------------------------|---------------------|
| `bin/console debug:container`  | `symfony-services`  |

### Benefits

- Direct access to compiled container
- Environment-aware (auto-detects dev/test/prod)
- Structured service ID → class mapping
