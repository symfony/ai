---
status: accepted
date: 2026-10-25
---

# 0004. Tool input schemas come from signatures and PHPDoc

## Context and Problem Statement

An agent calling `tools:call` needs each tool's parameters, their types and which are required.
The CLI also has to turn option strings back into typed PHP arguments. A separate, hand-written
schema would repeat what the method signature already says and would drift from it. Until 0.12
the `mcp/sdk` generated the schema; when Mate dropped the SDK (ADR 0001) it needed its own
generator and its own argument handling.

## Decision

`Discovery\SchemaGenerator::generate()` builds a JSON Schema from the method's reflection: one
property per parameter, `required` for parameters without a default, `default` from default
values, enum values from backed and unit enums, and `type: array` with `items` for variadic
parameters. The type comes from the `@param` tag (parsed by `Discovery\DocBlockParser`) when it
is more specific than the native type, otherwise from the native type. The tag text becomes the
`description`. Keyed generics such as `array<string, mixed>` map to `object`, integer-keyed
ones to `array`. Parameters without a type (or `mixed`) get an empty schema.

At call time `Invocation\ArgumentCaster` maps the named arguments onto the same signature,
casts strings to the declared types, fills in defaults and throws `InvalidArgumentException`
for a missing required argument or an unknown name. `tools:call` then suggests running
`tools:inspect <tool>`, and `tools:list` shows each tool's arguments (required first, optional
marked with `?`).

## Consequences

- **Good:** the signature is the single source for the schema shown by
  `tools:inspect`/`tools:list` and for the casting done by `tools:call`.
- **Good:** a wrong type or a misspelled argument fails with a clear message instead of
  reaching the tool code.
- **Trade-off:** schema quality depends on PHPDoc. A parameter without a `@param` text has no
  description, and only the type shapes the parser knows are expanded.
- **Trade-off:** the CLI passes strings, so non-scalar values need `--json`.

## Alternatives Considered

- **Hand-written schemas on the attribute**: not chosen; they repeat the signature and drift.
- **The `mcp/sdk` schema generator** (used from #1146 to 0.12): went away with the dependency
  in #2380.
- **Ignore unknown arguments**: rejected in `ArgumentCaster`, because silently dropping a typo
  returns a plausible but wrong result.

## History

- 2026-01-23, #1412 by @wachterjohannes (Johannes Wachter): `mcp:tools:call` takes JSON input and validates it against the SDK-generated schema.
- 2026-08-29, #2380 by @wachterjohannes (Johannes Wachter) and @lunetics: Mate's own `SchemaGenerator`, `DocBlockParser` and `ArgumentCaster`; parameters become long options.
- 2026-09-04, #2488 by @wachterjohannes (Johannes Wachter): `tools:list` shows an `Arguments` column and errors point to `tools:inspect`.

## References

- `src/Discovery/SchemaGenerator.php`: type resolution, nullability, enums, arrays, variadics.
- `src/Discovery/DocBlockParser.php`: `@param` tag extraction.
- `src/Invocation/ArgumentCaster.php`: casting, defaults, missing and unknown arguments.
- `src/Command/ToolsInspectCommand.php`, `src/Command/ToolsCallCommand.php`: where both are used.
- `CHANGELOG.md` (0.14): `Arguments` column in `tools:list`, `tools:inspect` hint in `tools:call`.
