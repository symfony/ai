---
status: accepted
date: 2026-10-25
---

# 0005. Agent instructions are generated into the project

## Context and Problem Statement

Without an MCP handshake (ADR 0001), a coding agent only knows Mate exists, and how to call it,
if something in the project tells it. Tests in PR #2380 showed this is decisive: the bare CLI
was called in 0 of 30 runs, while the same CLI with generated instructions and skills was used
and solved clearly more tasks. Each extension knows best which of its tools replace which shell
commands. The right command also differs per project, for example `ddev exec vendor/bin/mate`
when PHP runs in a container.

## Decision

`mate discover` (and `mate init`) write the instructions through
`Agent\AgentInstructionsMaterializer`:

- `mate/AGENT_INSTRUCTIONS.md` holds the instructions that `Agent\AgentInstructionsAggregator`
  collects from each enabled extension's `instructions` file and from the root project, with a
  fallback text when there are none.
- `AGENTS.md` gets a managed block between `<!-- BEGIN AI_MATE_INSTRUCTIONS -->` and
  `<!-- END AI_MATE_INSTRUCTIONS -->`: where it comes from, the command to use, how to list,
  inspect and call tools, and the installed extensions. Text outside the markers is left alone.
  If the markers are unbalanced or out of order, the file is not touched and a warning is
  logged.
- `CLAUDE.md` gets a managed `@AGENTS.md` import block, because Claude Code only reads
  `CLAUDE.md`. An existing `AGENTS.md` reference without markers counts as the user's own
  import.

The command comes from `mate.invocation` in `mate/config.php`, set by `mate init`. `mate init`
also pins `mate.php_version`, probed through that command, and `Runtime\PhpVersionGuard`
refuses to run under another PHP major.minor (`init`, `discover`, `list`, `help` and
`completion` only warn). The reason: run under the wrong interpreter, Mate does not fail, it
reports on a runtime that is not the application's. The guard turns that into an error.

## Consequences

- **Good:** agents read current, project-specific instructions instead of a fixed tool list,
  regenerated whenever extensions change.
- **Good:** extensions document their own tools next to the code (`INSTRUCTIONS.md`).
- **Trade-off:** Mate writes into user-owned files (`AGENTS.md`, `CLAUDE.md`). The markers are
  the contract; edits inside them are overwritten by the next `discover`.
- **Trade-off:** instructions are only as current as the last `discover`; the Composer plugin
  runs it after install and update.
- **Trade-off:** installed skills still say `vendor/bin/mate` rather than `mate.invocation`
  (tracked in #2445); the PHP version guard catches this only when the versions differ.

## Alternatives Considered

- **Send instructions in the MCP handshake** (#1425): the first way extension instructions
  reached agents; it went away with the MCP server in #2380.
- **Generated MCP configuration (`mcp.json`, `.mcp.json`, Codex wrappers)**: the PHP binary for
  containers lived there (#2271); replaced in 0.13 by `mate.invocation`.
- **Document the tools only in the package README**: not chosen; agents read the project's
  `AGENTS.md`/`CLAUDE.md`, not vendor READMEs, and a README cannot know the project's command.

## History

- 2026-01-23, #1425 by @wachterjohannes (Johannes Wachter): extensions ship agent instructions, sent to the agent in the MCP handshake.
- 2026-03-17, #1682 by @wachterjohannes (Johannes Wachter): `discover` writes `mate/AGENT_INSTRUCTIONS.md` and the managed `AGENTS.md` block.
- 2026-07-13, #2271 by @fatonh: `init` asks for the PHP binary so containerized setups (DDEV) work (fixes #2264).
- 2026-08-29, #2380 by @wachterjohannes (Johannes Wachter) and @lunetics: CLI-oriented instructions, the `CLAUDE.md` import, `mate.invocation` and the PHP version guard.
- 2026-08-29, #2454 by @wachterjohannes (Johannes Wachter): the PHP version is probed through the wrapper, so the guard no longer rejects the correct container command.
- 2026-08-30, #2456 by @wachterjohannes (Johannes Wachter): `init` no longer writes an invocation into `AGENTS.md` that it did not save.

## References

- `src/Agent/AgentInstructionsMaterializer.php`: the three files, markers, managed block text.
- `src/Agent/AgentInstructionsAggregator.php`: collection of extension and root instructions.
- `src/Runtime/PhpVersionGuard.php`: pinned PHP version and exempt commands.
- `src/Command/InitCommand.php`, `src/Command/DiscoverCommand.php`: where the files are written.
- `INSTRUCTIONS.md`: the core package's own instructions.
- `CHANGELOG.md` (0.7, 0.13): instruction materialization, `CLAUDE.md` import, `mate.invocation`.
