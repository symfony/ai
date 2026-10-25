---
status: accepted
date: 2026-10-25
---

# 0001. The bundle is a thin integration of the official MCP PHP SDK

## Context and Problem Statement

The Model Context Protocol (MCP) has sessions, transports and new versions of the
specification every few months. A Symfony application wants to be an MCP server and an MCP
client without building the protocol itself.

## Decision

The bundle uses the official MCP PHP SDK (`mcp/sdk`) and contains no protocol code of its
own. It only creates SDK objects as Symfony services:

- for each server, an SDK `Builder` (`mcp.server.<name>.builder`) and the `Server` it builds
  (`mcp.server.<name>`);
- for each client connection, an SDK `Client` with its STDIO or HTTP transport;
- SDK session stores, plus `FrameworkSessionStore`, which uses Symfony's session handler.

Services implementing the SDK's `LoaderInterface`, `RequestHandlerInterface` or
`NotificationHandlerInterface` are added to every server automatically. On top of that, the
bundle adds Symfony features: configuration, routes, console commands, the profiler panel,
`debug:mcp`, and MCP Apps rendered with Twig.

## Consequences

- **Good:** protocol behavior and new specification versions come from the SDK.
- **Good:** applications can add SDK handlers as normal services.
- **Trade-off:** the bundle follows the SDK's releases; an SDK API change means a bundle
  change. The bundle is marked experimental.

## Alternatives Considered

- **Implementing the protocol in the bundle**: not chosen, because it would duplicate the SDK
  maintained by the MCP project.

## References

- `composer.json`: the `mcp/sdk` requirement.
- `src/McpBundle.php`: `configureServer()`, `configureClients()`, `loadExtension()`.
- `src/Client/TransportFactory.php`: the client transports.
