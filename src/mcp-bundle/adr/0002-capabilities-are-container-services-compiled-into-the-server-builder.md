---
status: accepted
date: 2026-10-25
---

# 0002. Capabilities are container services compiled into the server builder

## Context and Problem Statement

The SDK can find tools, prompts and resources by scanning folders at runtime. In Symfony
these elements are services with dependencies, and the container already knows every class.
Scanning files on each request is wasted work. Also, with several servers, each server must
expose only its own elements.

## Decision

Elements are found through the container. The SDK attributes `#[McpTool]`, `#[McpPrompt]`,
`#[McpResource]` and `#[McpResourceTemplate]` are autoconfigured and tag the service.

`McpPass` reads each tagged method once, when the container is built. It creates the tool's
input schema and adds the element to the builder of each server that exposes it. This is
stored in the compiled container. At runtime, a service is only created when its element is
actually called. `McpAppPass` does the same for MCP Apps.

Each server must have a `registry` option that says what it exposes. Patterns are `*`, a
service id, a class name, or a namespace ending in `\`. A pattern that matches nothing fails
the build, because it is almost always a typo. Elements no server uses are shown by
`debug:mcp`.

## Consequences

- **Good:** no file scanning at runtime, services created only when needed, and normal
  dependency injection.
- **Good:** each server's elements are explicit, and mistakes show up at build time.
- **Trade-off:** an element must be a registered service, and attribute changes need a cache
  rebuild.

## Alternatives Considered

- **The SDK's folder scanning**: used before 0.12, replaced by the container approach.
- **Every element on every server**: replaced in 0.13 by the required `registry` option.

## References

- `src/McpBundle.php`: `registerMcpAttributes()`.
- `src/DependencyInjection/McpPass.php`: build-time registration.
- `src/DependencyInjection/ElementMatcher.php`: the `registry` patterns.
- `config/options.php`: the `registry` option.
