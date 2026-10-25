---
status: accepted
date: 2026-10-25
---

# 0001. Mate is a native CLI, not an MCP server

## Context and Problem Statement

Up to 0.12, Mate ran an MCP server on top of `mcp/sdk` (`serve`/`stop` commands, a generated
`mcp.json`/`.mcp.json`, Codex wrappers). An MCP client loads every tool schema into the agent's
context up front, and the server had to be configured and kept alive per agent. Coding agents
(Claude Code, Codex, Cursor, ...) can all run shell commands, so the question was whether the
protocol layer was earning its cost for a development-only tool.

## Decision

Mate is a plain Symfony Console application, `vendor/bin/mate`, that agents invoke directly.
`bin/mate.php` builds the container through `Container\ContainerFactory` and the console through
`App::build()`. Capabilities are exposed as commands: `tools:list`, `tools:inspect`, `tools:call`
and `resources:read`, plus `debug:capabilities` and `debug:extensions`. `tools:call` takes tool
parameters as long options (`--limit=1`, repeated for variadic parameters) with `--json` as the
escape hatch for nested values and for names taken by console flags. Output is `pretty` by
default, with `--format=json` and, when `helgesverre/toon` is installed, `--format=toon`.
There is no `mcp/sdk` dependency and no long-running process.

## Consequences

- **Good:** schemas are read on demand (`tools:inspect`, `--help`) instead of being loaded into
  the agent's context for every session.
- **Good:** nothing to configure per agent and no server to start or stop; any agent that can run
  a shell command can use Mate, including through a wrapper such as `ddev exec vendor/bin/mate`.
- **Good:** every tool is also usable by a developer at the terminal.
- **Trade-off:** MCP prompts had no native equivalent and were removed, together with
  `debug:capabilities --type=prompt`.
- **Trade-off:** agents learn about Mate from generated instructions (see ADR 0005), not from a
  protocol handshake; an agent that never reads them does not know the tools exist.
- **Trade-off:** each call boots a fresh PHP process and container; the build-time
  `ReflectionDiscoverer` is reused within that process, but nothing is shared across calls.

## Alternatives Considered

- **Keep the MCP server**: not chosen because of the up-front schema cost, the per-agent
  configuration (`mcp.json`, `.mcp.json`, Codex wrappers) and the process lifecycle
  (`serve`/`stop`, `--force-keep-alive`) it required.
- **Offer both MCP and CLI**: not chosen; the 0.13 release removed the MCP runtime entirely
  rather than maintaining two front ends over the same capabilities.

## References

- `bin/mate.php`: builds the container and the console application, no server loop.
- `src/App.php`: console application builder.
- `src/Command/ToolsCallCommand.php`: option-based parameters, `--json`, output formats.
- `src/Command/ToolsInspectCommand.php`, `src/Command/ToolsListCommand.php`: on-demand schema.
- `CHANGELOG.md` (0.13): "Replace the MCP server with a native CLI".
- Repository root `UPGRADE.md` (Mate section, 0.12 to 0.13): removal of `serve`/`stop`, `mcp.json` and the command renames.
