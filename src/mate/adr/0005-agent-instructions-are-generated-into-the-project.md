---
status: accepted
date: 2026-10-25
---

# 0005. Agent instructions are generated into the project

## Context and Problem Statement

Without an MCP handshake (ADR 0001), a coding agent only knows Mate exists and how to call it if
something in the project tells it. Each extension knows best which of its tools replace which
shell commands, and the right invocation differs per project (for example `ddev exec
vendor/bin/mate` when the application runs in a container).

## Decision

`mate discover` (and `mate init`) materialize the instructions through
`Agent\AgentInstructionsMaterializer`:

- `mate/AGENT_INSTRUCTIONS.md` holds the extension instructions collected by
  `Agent\AgentInstructionsAggregator` from each enabled extension's `instructions` file and the
  root project's, with a fallback text when none exist.
- `AGENTS.md` gets a managed block between `<!-- BEGIN AI_MATE_INSTRUCTIONS -->` and
  `<!-- END AI_MATE_INSTRUCTIONS -->`: provenance, the invocation to use, how to list, inspect
  and call tools, and the installed extensions. Content outside the markers is left alone; with
  unbalanced or out-of-order markers the file is not touched and a warning is logged.
- `CLAUDE.md` gets a managed `@AGENTS.md` import block, because Claude Code only reads
  `CLAUDE.md`. An existing `AGENTS.md` reference without markers counts as the user's own import.

The invocation comes from `mate.invocation` in `mate/config.php`, set by `mate init`.
`mate init` also pins `mate.php_version`, and `Runtime\PhpVersionGuard` refuses to run under
another PHP major.minor (except `init`, `discover`, `list`, `help` and `completion`, which warn),
so an agent that ignores the stated invocation gets an error instead of facts about the wrong
runtime.

## Consequences

- **Good:** agents read current, project-specific instructions instead of a hardcoded tool list,
  and they are regenerated whenever extensions change.
- **Good:** extensions document their own tools next to the code (`INSTRUCTIONS.md`).
- **Trade-off:** Mate writes into user-owned files (`AGENTS.md`, `CLAUDE.md`); the markers are
  the contract, and editing inside them is overwritten by the next `discover`.
- **Trade-off:** instructions are only as current as the last `discover`; the Composer plugin
  runs it after install and update to keep that window small.

## Alternatives Considered

- **Generated MCP configuration (`mcp.json`, `.mcp.json`, Codex wrappers)**: replaced in 0.13
  together with the MCP server.
- **Document the tools only in the package README**: not chosen; agents read the project's
  `AGENTS.md`/`CLAUDE.md`, not vendor READMEs, and the README cannot know the invocation.

## References

- `src/Agent/AgentInstructionsMaterializer.php`: the three files, markers, managed block text.
- `src/Agent/AgentInstructionsAggregator.php`: collection of extension and root instructions.
- `src/Runtime/PhpVersionGuard.php`: pinned PHP version and exempt commands.
- `src/Command/InitCommand.php`, `src/Command/DiscoverCommand.php`: where materialization runs.
- `INSTRUCTIONS.md`: the core package's own instructions.
- `CHANGELOG.md` (0.7, 0.13): instruction materialization, `CLAUDE.md` import, `mate.invocation`.
