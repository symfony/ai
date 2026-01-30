## Monolog Bridge

Use MCP tools instead of CLI for log analysis:

| Instead of...                     | Use                                |
|-----------------------------------|------------------------------------|
| `tail -f var/log/dev.log`         | `monolog-tail`                     |
| `grep "error" var/log/*.log`      | `monolog-search` with term "error" |
| `grep -E "pattern" var/log/*.log` | `monolog-search-regex`             |

### Benefits

- Structured output with parsed log entries
- Multi-file search across all logs at once
- Filter by environment, level, or channel
