---
status: accepted
date: 2026-10-25
---

# 0001. Mate is a native CLI, not an MCP server

## Context and Problem Statement

Mate started in December 2025 as an MCP server built on `mcp/sdk`. It had `serve`/`stop`
commands, and `mate init` generated `mcp.json`/`.mcp.json` and Codex wrappers. This had costs:
an MCP client loads every tool schema into the agent's context at the start, and each agent had
to be configured and keep a server process running. Every coding agent (Claude Code, Codex,
Cursor, ...) can already run shell commands, so the question was whether the protocol layer was
worth it for a development-only tool.

A test in PR #2380 showed the transport itself does not change results: the MCP server and the
CLI with skills solved about the same share of tasks. It also showed that a bare CLI is not used
at all: agents never called it until instructions and skills told them about it (ADR 0005).

## Decision

Mate is a plain Symfony Console application, `vendor/bin/mate`, that agents call directly.
`bin/mate.php` builds the container with `Container\ContainerFactory` and the console with
`App::build()`. Capabilities are commands: `tools:list`, `tools:inspect`, `tools:call` and
`resources:read`, plus `debug:capabilities` and `debug:extensions`. `tools:call` takes tool
parameters as long options (`--limit=1`, repeated for variadic parameters). `--json` is the
fallback for nested values and for names that clash with console options. Output is `pretty`
by default, with `--format=json` and, when `helgesverre/toon` is installed, `--format=toon`.
There is no `mcp/sdk` dependency and no long-running process.

Mate keeps the tool, resource and resource-template split. Models already know these concepts,
tools carry a typed input schema (ADR 0004), and resources let an agent read one part of a
profile instead of all of it.

## Consequences

- **Good:** schemas are read on demand (`tools:inspect`, `--help`), not loaded into the
  agent's context in every session.
- **Good:** nothing to configure per agent and no server to start or stop. Any agent that can
  run a shell command can use Mate, also through a wrapper such as `ddev exec vendor/bin/mate`.
- **Good:** a developer can run every tool at the terminal too.
- **Trade-off:** MCP prompts had no native equivalent and were removed, together with
  `debug:capabilities --type=prompt`.
- **Trade-off:** agents learn about Mate only from generated instructions (ADR 0005). An agent
  that never reads them does not know the tools exist.
- **Trade-off:** each call starts a new PHP process and container; nothing is shared between
  calls.
- **Trade-off:** a breaking change for anyone who scripted `mate serve` or the `mcp:*` commands;
  `UPGRADE.md` lists every rename.

## Alternatives Considered

- **Keep the MCP server** (#1146, the original design): not chosen because of the up-front schema
  cost, the per-agent setup (`mcp.json`, `.mcp.json`, Codex wrappers from #1682) and the server
  process (`serve`/`stop`, `--force-keep-alive` from #1280).
- **Offer both MCP and CLI**: not chosen. The `mcp:tools:*` commands (#1379, #1412) had already
  run tools from the CLI next to the server; 0.13 removed the server instead of keeping two
  front ends.
- **Use the existing `bin/console debug:*` commands**: raised in the #2380 review. Not chosen for
  now: those boot the kernel, while Mate reads the compiled container and profiler files from
  disk, so it still works when the application does not boot, and its output is shaped for
  agents.

## History

- 2025-12-21, #1146 by @wachterjohannes (Johannes Wachter): adds Mate as a standalone MCP server over stdio.
- 2025-12-28, #1280 by @Nyholm (Tobias Nyholm): adds `stop` and `--force-keep-alive` for the server process.
- 2026-01-13 to 2026-01-23, #1379 and #1412 by @wachterjohannes (Johannes Wachter): add `mcp:tools:list` and `mcp:tools:call`, the first CLI access to tools.
- 2026-03-31, #1805 by @ineersa (Illia Vasylevskyi): adds TOON output to the commands to save tokens.
- 2026-08-29, #2380 by @wachterjohannes (Johannes Wachter) and @lunetics: replaces the MCP server with the native CLI, backed by agent tests.

## References

- `bin/mate.php`: builds the container and the console application, no server loop.
- `src/App.php`: console application builder.
- `src/Command/ToolsCallCommand.php`: option-based parameters, `--json`, output formats.
- `src/Command/ToolsInspectCommand.php`, `src/Command/ToolsListCommand.php`: on-demand schema.
- `CHANGELOG.md` (0.13): "Replace the MCP server with a native CLI".
- Repository root `UPGRADE.md` (Mate section, 0.12 to 0.13): removal of `serve`/`stop`, `mcp.json` and the command renames.
