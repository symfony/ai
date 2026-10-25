---
status: accepted
date: 2026-10-25
---

# 0002. Capabilities are attributed methods discovered by reflection

## Context and Problem Statement

Mate needs a way for extensions and the project itself to contribute tools and resources without
registering them by hand. After dropping MCP (ADR 0001), the `mcp/sdk` attributes were no longer
available, so Mate needed its own declaration mechanism and its own discovery.

## Decision

A capability is a public method carrying one of Mate's native attributes:
`Attribute\MateTool` (`name`, `title`, `description`), `Attribute\MateResource` or
`Attribute\MateResourceTemplate`. `Discovery\ReflectionDiscoverer` walks the configured scan
directories with Symfony Finder, extracts the declared class name of each `*.php` file with
`token_get_all()` (ignoring `Foo::class` tokens), reflects its methods and builds
`ToolDefinition`, `ResourceDefinition` and `ResourceTemplateDefinition` models. A file that fails
to load is logged and skipped, so one broken class does not truncate discovery.

`Container\ContainerFactory::registerServices()` runs this discovery at container build time and
registers each handler class as a public, autowired service (or makes an existing definition
public). At call time `Invocation\HandlerInvoker` resolves the handler from the container and
throws `HandlerNotWiredException` when it is missing, instead of constructing it blindly.
`Discovery\CapabilityRegistry` memoizes discovery per extension and applies the
`mate.disabled_features` configuration.

## Consequences

- **Good:** a tool is just a method with an attribute; dependencies arrive through autowiring.
- **Good:** no generated registry or manual service tags to keep in sync with the code.
- **Trade-off:** discovery depends on the file layout (one class per file, inside a scan
  directory) and on the Composer autoloader; a class outside the scan directories is silently
  not a capability, which is why the error message points to `discover` and `dump-autoload`.
- **Trade-off:** scanning and reflecting runs on every container build; the discoverer instance
  is handed to the runtime as a synthetic service so it is not repeated within one invocation.

## Alternatives Considered

- **Keep the `mcp/sdk` attributes**: not possible once the dependency was removed.
- **Explicit registration (service tags or a configuration list)**: not chosen; the attribute
  keeps the declaration next to the code it describes.

## References

- `src/Attribute/MateTool.php`, `src/Attribute/MateResource.php`,
  `src/Attribute/MateResourceTemplate.php`: the declaration API.
- `src/Discovery/ReflectionDiscoverer.php`: Finder scan, token-based class detection, reflection.
- `src/Container/ContainerFactory.php`: handler registration as autowired public services.
- `src/Invocation/HandlerInvoker.php`: container resolution and `HandlerNotWiredException`.
- `src/Discovery/CapabilityRegistry.php`: per-extension memoization and feature filtering.
- `CHANGELOG.md` (0.13): native `#[MateTool]`, `#[MateResource]`, `#[MateResourceTemplate]`.
