---
status: accepted
date: 2026-10-25
---

# 0002. Capabilities are attributed methods discovered by reflection

## Context and Problem Statement

Extensions and the project itself must be able to add tools and resources without registering
them by hand. Until 0.12 Mate used the `mcp/sdk` attributes (`#[McpTool]`, ...) and the SDK's
own discovery. When Mate dropped MCP (ADR 0001), those attributes went with the dependency, so
Mate needed its own attributes and its own discovery.

## Decision

A capability is a public method with one of Mate's attributes: `Attribute\MateTool` (`name`,
`title`, `description`), `Attribute\MateResource` or `Attribute\MateResourceTemplate`. The
`Mate` prefix keeps them apart from the Agent component's `AsTool`. `name` is required and the
attributes apply to methods only.

`Discovery\ReflectionDiscoverer` scans the configured directories with Symfony Finder. For each
`*.php` file it reads the declared class name with `token_get_all()` (ignoring `Foo::class`),
reflects the methods and builds `ToolDefinition`, `ResourceDefinition` and
`ResourceTemplateDefinition` objects. A file that fails to load is logged and skipped, so one
broken class does not stop discovery.

`Container\ContainerFactory::registerServices()` runs discovery when the container is built and
registers each handler class as a public, autowired service. At call time
`Invocation\HandlerInvoker` gets the handler from the container and throws
`HandlerNotWiredException` when it is missing, instead of creating it blindly.
`Discovery\CapabilityRegistry` caches discovery per extension and applies
`mate.disabled_features`.

## Consequences

- **Good:** a tool is just a method with an attribute; its dependencies come from autowiring.
- **Good:** no generated registry or service tags to keep in sync with the code.
- **Trade-off:** discovery depends on the file layout (one class per file, inside a scan
  directory) and on the Composer autoloader. A class outside the scan directories is silently
  ignored, which is why the error message points to `discover` and `dump-autoload`.
- **Trade-off:** scanning runs on every container build. The discoverer is passed to the
  runtime as a synthetic service so it does not run twice in one call.

## Alternatives Considered

- **Keep the `mcp/sdk` attributes and discovery** (used from #1146 to 0.12): not possible once
  the dependency was removed in #2380.
- **Explicit registration (service tags or a configuration list)**: not chosen; the attribute
  keeps the declaration next to the code.

## History

- 2025-12-21, #1146 by @wachterjohannes (Johannes Wachter): tools are `#[McpTool]` methods found by the `mcp/sdk` discovery and registered as autowired services.
- 2025-12-28, #1260 by @Nyholm (Tobias Nyholm): `ContainerFactory` returns a compiled container, so environment variables are resolved.
- 2025-12-30, #1319 by @wachterjohannes (Johannes Wachter): capabilities can be loaded per extension, so each one can be inspected on its own.
- 2026-08-29, #2380 by @wachterjohannes (Johannes Wachter) and @lunetics: native `#[MateTool]`, `#[MateResource]`, `#[MateResourceTemplate]` and `ReflectionDiscoverer` replace the SDK.

## References

- `src/Attribute/MateTool.php`, `src/Attribute/MateResource.php`,
  `src/Attribute/MateResourceTemplate.php`: the declaration API.
- `src/Discovery/ReflectionDiscoverer.php`: Finder scan, token-based class detection, reflection.
- `src/Container/ContainerFactory.php`: handler registration as autowired public services.
- `src/Invocation/HandlerInvoker.php`: container resolution and `HandlerNotWiredException`.
- `src/Discovery/CapabilityRegistry.php`: per-extension caching and feature filtering.
- `CHANGELOG.md` (0.13): native `#[MateTool]`, `#[MateResource]`, `#[MateResourceTemplate]`.
