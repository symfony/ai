## Symfony Bridge

### Container Introspection

| Instead of...                  | Use                |
|--------------------------------|--------------------|
| `bin/console debug:container`  | `symfony-services` |

- Direct access to compiled container
- Environment-aware (auto-detects dev/test/prod)
- Supports filtering by service ID or class name via query parameter

### Profiler Access

When `symfony/http-kernel` is installed, profiler tools become available:

| Tool                        | Description                                             |
|-----------------------------|---------------------------------------------------------|
| `symfony-profiler-list`     | List and filter profiles by method, URL, IP, status, date range |
| `symfony-profiler-get`      | Get profile by token                                    |

**Resources:**
- `symfony-profiler://profile/{token}` - Full profile with collector list
- `symfony-profiler://profile/{token}/{collector}` - Collector-specific data

**Security:** Cookies, session data, auth headers, and sensitive env vars are automatically redacted.
