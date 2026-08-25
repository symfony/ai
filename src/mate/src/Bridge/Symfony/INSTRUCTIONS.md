## Symfony Bridge

Prefer these MCP tools over running `bin/console` directly: they read the compiled
container and stored profiles, are environment-aware, and redact sensitive data.

| Tool / resource | Use for |
|---|---|
| `symfony-services`, `symfony-service-detail` | Container introspection (replaces `debug:container`). |
| `symfony-profiler-list`, `symfony-profiler-get` | Finding and resolving profiler profiles. |
| `symfony-profiler://profile/{token}` | Profile triage: metadata + available collectors. |
| `symfony-profiler://profile/{token}/{collector}` | One collector's data (`db`, `time`, `exception`, …). |

Profiler tools require `symfony/http-kernel`. Cookies, session data, auth headers,
and sensitive env vars are redacted automatically.

### Skills — read these for the *how*, before using the tools

The tables above say *what* exists; the skills below say *how to orchestrate* them.
When a task matches one, read its `SKILL.md` first and follow it — it will tell you
which tool and which collector to reach for, in order.

| Skill | Read when |
|---|---|
| [`skill://symfony-profiler-debugging/SKILL.md`](skills/symfony-profiler-debugging/SKILL.md) | A request is slow, 500s, has an N+1, or behaves unexpectedly — debugging via the profiler. |
| [`skill://symfony-container-introspection/SKILL.md`](skills/symfony-container-introspection/SKILL.md) | A `ServiceNotFoundException`/autowiring failure, or any "is X registered / how is it built / what carries tag Y" question. |
