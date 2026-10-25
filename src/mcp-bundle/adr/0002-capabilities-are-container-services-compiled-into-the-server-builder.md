---
status: accepted
date: 2026-10-25
---

# 0002. Capabilities are container services compiled into the server builder

## Context and Problem Statement

The SDK can find tools, prompts, resources and resource templates by scanning directories
for its attributes at runtime. In a Symfony application those elements are services with
dependencies, the container already knows every class, and scanning files on each request
is wasted work. With several servers per application, each server also has to expose only
its own elements.

## Decision

Elements are discovered through the container instead of the SDK's file-based discovery.
`McpBundle::registerMcpAttributes()` autoconfigures the SDK attributes `#[McpTool]`,
`#[McpPrompt]`, `#[McpResource]` and `#[McpResourceTemplate]` on classes and methods, tagging
the service `mcp.tool`, `mcp.prompt`, `mcp.resource` or `mcp.resource_template` with the
method (`__invoke` for a class-level attribute, which then must exist).

`DependencyInjection\McpPass` reflects each tagged method once at compile time, generates
the tool input schema with the SDK's `SchemaGenerator`, and adds `addTool()`, `addPrompt()`,
`addResource()` or `addResourceTemplate()` calls with the attribute metadata to the
builder of every server that exposes the element. The result is cached in the dumped
container. At runtime the SDK resolves handlers from a per-server service locator keyed by
class name (and service id), so an element's service is only instantiated when it is
called. `McpAppPass` does the same for `#[AsMcpApp]` and `#[AsMcpAppTool]` and runs first.

Every server must declare a `registry`: one pattern list for all kinds or a map per kind
(`tools`, `prompts`, `resources`, `resource_templates`, `apps`). `ElementMatcher` matches a
pattern against the service id and the class: `*`, an exact id or class, or a namespace
prefix ending in a backslash. An element matched by no server is not an error and is
listed by `debug:mcp`; a pattern that matches nothing fails the build as a likely typo.

## Consequences

- **Good:** no filesystem scan at runtime, lazy handler instantiation, and constructor
  injection for every element.
- **Good:** what each server exposes is explicit, and errors (missing method, missing
  attribute, unknown class, schema generation failure) surface at container build time.
- **Trade-off:** an element must be a registered, autoconfigured service; a class outside
  the service definitions is never exposed.
- **Trade-off:** metadata is frozen at compile time, so attribute changes need a cache
  rebuild.

## Alternatives Considered

- **The SDK's file-based discovery**: used before 0.12 and replaced because it scans
  directories instead of using the container's knowledge of services.
- **Exposing every element on every server**: replaced in 0.13 by the required `registry`
  option when multiple servers per application were introduced.

## References

- `src/McpBundle.php`: `registerMcpAttributes()` and `configureServers()` (the
  `mcp.servers.elements` parameter).
- `src/DependencyInjection/McpPass.php`: compile-time reflection, schema generation and the
  per-server handler locator.
- `src/DependencyInjection/ElementMatcher.php`: pattern matching and unused-pattern
  detection.
- `src/DependencyInjection/McpAppPass.php`: the same approach for MCP Apps.
- `config/options.php`: the `registry` option.
