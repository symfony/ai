---
status: accepted
date: 2026-10-25
---

# 0001. The bundle is a thin integration of the official MCP PHP SDK

## Context and Problem Statement

The Model Context Protocol (MCP) has sessions, transports and new versions of the
specification every few months. A Symfony application wants to be an MCP server and an MCP
client without building the protocol itself. Since September 2025 the MCP project has an
official PHP SDK.

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

- **Our own SDK**: the bundle started on `php-llm/mcp-sdk`, which became `symfony/mcp-sdk`
  in the monorepo. Replaced in #637 by the official SDK once it was launched, and the
  internal SDK was deleted.

## History

- 2025-03-15, 55d1842f by Christopher Hertel: first MCP bundle, built on
  `php-llm/mcp-sdk`.
- 2025-06-13, 5b03ae81 by Christopher Hertel: moved into the Symfony AI monorepo next to the
  internal `symfony/mcp-sdk`.
- 2025-09-24, #637 by @camilleislasse: switched to the official `mcp/sdk`, as asked in
  #526 after the official SDK was launched, and removed the internal SDK.
- 2025-12-08, #1085 and #1091 by @soyuka: SDK loaders, then request and notification
  handlers are autoconfigured and passed to the server.
- 2026-04-11, #1838 by @simonchrz (Simon Chrzanowski) and @chr-hertel: `FrameworkSessionStore`
  backed by Symfony's `SessionHandlerInterface`.
- 2026-08-29, #2451 by @chr-hertel (Christopher Hertel): support for the 2026-07-28
  specification revision on the existing endpoint, served by the SDK.

## References

- `composer.json`: the `mcp/sdk` requirement.
- `src/McpBundle.php`: `configureServer()`, `configureClients()`, `loadExtension()`.
- `src/Client/TransportFactory.php`: the client transports.
