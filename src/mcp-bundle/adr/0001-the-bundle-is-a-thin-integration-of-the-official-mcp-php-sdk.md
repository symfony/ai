---
status: accepted
date: 2026-10-25
---

# 0001. The bundle is a thin integration of the official MCP PHP SDK

## Context and Problem Statement

The Model Context Protocol defines a JSON-RPC protocol with sessions, capability
negotiation, several transports and dated specification revisions that keep changing. A
Symfony application wants to act as an MCP server and as an MCP client without
re-implementing that protocol, and without the bundle having to follow every revision of
the specification itself.

## Decision

The bundle depends on the official MCP PHP SDK (`mcp/sdk` in `composer.json`) and contains
no protocol implementation of its own. It only builds and wires SDK objects as services:

- per server, an `Mcp\Server\Builder` (`mcp.server.<name>.builder`, created with
  `Server::builder()`) configured through method calls (`setServerInfo`, `setRegistry`,
  `setSession`, `setEventDispatcher`, `addRequestHandlers`, protocol versions, cache and
  request-state options), and the `Mcp\Server` it builds (`mcp.server.<name>`);
- per client connection, an `Mcp\Client\Builder` and the `Mcp\Client` it builds, with the
  SDK's `StdioTransport` or `HttpTransport` created by `Client\TransportFactory`;
- SDK session stores (`InMemorySessionStore`, `FileSessionStore`, `Psr16SessionStore`) and
  locks, plus `Session\FrameworkSessionStore` to back sessions with Symfony's
  `SessionHandlerInterface`.

Extension points of the SDK are exposed through autoconfiguration: services implementing
`LoaderInterface`, `RequestHandlerInterface` or `NotificationHandlerInterface` are tagged
`mcp.loader`, `mcp.request_handler` and `mcp.notification_handler` and passed to every
server builder. What the bundle adds is Symfony glue: configuration, routing, console
commands, the profiler panel (`Profiler\DataCollector`), `debug:mcp`, and MCP Apps rendered
with Twig.

## Consequences

- **Good:** protocol behavior, including new specification revisions such as 2026-07-28,
  comes from the SDK; the bundle mostly adds configuration for what the SDK offers.
- **Good:** applications can plug in SDK-level handlers and loaders as ordinary services.
- **Trade-off:** the bundle follows the SDK's release cadence and API; an SDK change in a
  builder signature is a bundle change, and the bundle is marked experimental.
- **Trade-off:** concerns the SDK leaves out stay out of the bundle as well; for example
  the bundle acts as an OAuth resource server only and delegates the authorization server
  role to Symfony Security and other packages.

## Alternatives Considered

- **A protocol implementation inside the bundle**: not chosen because it would duplicate the
  SDK maintained by the MCP project and drift from the specification.

## References

- `composer.json`: the `mcp/sdk` requirement.
- `src/McpBundle.php`: `configureServer()`, `configureClients()`, `clientBuilder()`,
  `configureSessionStore()` and the autoconfiguration in `loadExtension()`.
- `src/Client/TransportFactory.php`: creation of the SDK client transports.
- `config/services.php`: the shared PSR-7/PSR-17 factories.
- `CHANGELOG.md`: 0.13, support for the 2026-07-28 revision served by the SDK.
