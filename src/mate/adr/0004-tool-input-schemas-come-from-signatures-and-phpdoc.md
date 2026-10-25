---
status: accepted
date: 2026-10-25
---

# 0004. Tool input schemas come from signatures and PHPDoc

## Context and Problem Statement

An agent calling `tools:call` needs to know each tool's parameters, their types and whether they
are required, and the CLI has to turn option strings back into typed PHP arguments. Asking tool
authors to write and maintain a separate schema would duplicate what the method signature
already states and would drift from it.

## Decision

`Discovery\SchemaGenerator::generate()` derives a JSON Schema object from the method's
reflection: one property per parameter, `required` for parameters that are not optional,
`default` from default values, enum constraints from backed and unit enums, and `type: array`
with `items` for variadic parameters. The type comes from the `@param` tag parsed by
`Discovery\DocBlockParser` when it is more specific than the native type, otherwise from the
reflection type; the tag text becomes the `description`. Keyed generics such as
`array<string, mixed>` map to `object`, integer-keyed ones to `array`. Unconstrained parameters
(`mixed`, untyped) produce an empty schema object.

At call time `Invocation\ArgumentCaster` maps the name-keyed argument bag onto the same
signature, casts scalar strings to the declared types, fills defaults and throws
`InvalidArgumentException` for a missing required argument or an unknown name. `tools:call`
adds a hint to run `tools:inspect <tool>` in that case.

## Consequences

- **Good:** the signature is the single source of truth for both the schema shown by
  `tools:inspect`/`tools:list` and the casting done by `tools:call`.
- **Good:** an unknown or misspelled argument fails loudly instead of returning an answer
  computed from different inputs.
- **Trade-off:** schema quality depends on PHPDoc; a parameter without a `@param` description
  has no description, and only the type shapes the parser understands are expanded.
- **Trade-off:** the CLI passes strings, so values that are not scalars need `--json`.

## Alternatives Considered

- **Hand-written schemas on the attribute**: not chosen; they duplicate the signature and drift.
- **Ignore unknown arguments**: rejected in `ArgumentCaster`, since silently dropping a typo
  returns a plausible but wrong result.

## References

- `src/Discovery/SchemaGenerator.php`: type resolution, nullability, enums, arrays, variadics.
- `src/Discovery/DocBlockParser.php`: `@param` tag extraction.
- `src/Invocation/ArgumentCaster.php`: casting, defaults, missing and unknown arguments.
- `src/Command/ToolsInspectCommand.php`, `src/Command/ToolsCallCommand.php`: where both are used.
- `CHANGELOG.md` (0.14): `Arguments` column in `tools:list`, `tools:inspect` hint in `tools:call`.
