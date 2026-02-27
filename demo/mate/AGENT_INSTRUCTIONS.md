## AI Mate Agent Instructions

This MCP server provides specialized tools for PHP development.
The following extensions are installed and provide MCP tools that you should
prefer over running CLI commands directly.

---

### Monolog Bridge

Use MCP tools instead of CLI for log analysis:

| Instead of...                     | Use                                |
|-----------------------------------|------------------------------------|
| `tail -f var/log/dev.log`         | `monolog-tail`                     |
| `grep "error" var/log/*.log`      | `monolog-search` with term "error" |
| `grep -E "pattern" var/log/*.log` | `monolog-search-regex`             |

#### Benefits

- Structured output with parsed log entries
- Multi-file search across all logs at once
- Filter by environment, level, or channel

---

### Symfony Bridge

#### Container Introspection

| Instead of...                  | Use                 |
|--------------------------------|---------------------|
| `bin/console debug:container`  | `symfony-services`  |

- Direct access to compiled container
- Environment-aware (auto-detects dev/test/prod)

#### Profiler Access

When `symfony/http-kernel` is installed, profiler tools become available:

| Tool                        | Description                                |
|-----------------------------|--------------------------------------------|
| `symfony-profiler-list`     | List profiles with optional filtering      |
| `symfony-profiler-latest`   | Get the most recent profile                |
| `symfony-profiler-search`   | Search by route, method, status, date      |
| `symfony-profiler-get`      | Get profile by token                       |

**Resources:**
- `symfony-profiler://profile/{token}` - Full profile with collector list
- `symfony-profiler://profile/{token}/{collector}` - Collector-specific data

**Security:** Cookies, session data, auth headers, and sensitive env vars are automatically redacted.
