---
status: accepted
date: 2026-10-25
---

# 0003. Named, isolated servers and lazily connected clients

## Context and Problem Statement

An application may expose more than one MCP server (for example a public one and an
internal one behind another firewall) and may itself call remote MCP servers. Each server
needs its own identity, transports and sessions, and must not accept the sessions of
another. A client connection, on the other hand, owns a child process or an HTTP session
that must not outlive a request or a worker message.

## Decision

Servers are configured under `mcp.servers.<name>` and clients under `mcp.clients.<name>`;
every service id carries those names.

For each server the bundle registers its own registry, session store, builder and
`mcp.server.<name>` service, aliased for autowiring as `Server $<name>Server`. Transports
are chosen per server under `transports`:

- **HTTP** (default on): `Routing\RouteLoader` (type `mcp`) adds one route per server at
  `http.path`, defaulting to `/mcp/<name>` regardless of how many servers exist.
  `Controller\McpController` converts the request to PSR-7, runs the server with the SDK's
  `StreamableHttpTransport` and converts the response back, streaming it for
  `text/event-stream`. `Http\MiddlewareFactory` keeps the SDK's DNS rebinding protection,
  tailored by `http.allowed_hosts` and `http.allowed_origins`.
- **STDIO** (default off): the `mcp:server [name]` command runs one server per process,
  because the transport owns STDIN and STDOUT.

`McpBundle::assertServersAreIsolated()` rejects two servers on the same HTTP path or on the
same session storage, because the SDK does not namespace session ids by server.

For each remote server of a client the bundle registers a `Client\ServerConnection`
(`mcp.client.<client>.server.<server>`) and per client a `Client\McpClient`
(`mcp.client.<client>`, autowired as `McpClientInterface $<client>`). `ServerConnection`
connects on the first request and disconnects on `kernel.reset`, so a long-running worker
never carries a child process or a stale session into the next message. Client capabilities
such as roots, sampling and elicitation are advertised only when a handler service is
configured for them.

## Consequences

- **Good:** servers with different audiences can live in one application, each behind its own
  route and firewall, with session leakage between them rejected at build time.
- **Good:** application code calls remote servers without handling `connect()` or process
  lifetime itself; the AI Bundle builds its `mcp_server` tool entries on these connections.
- **Trade-off:** every server needs a `registry` and, when several are configured, distinct
  paths and session storage, which is more configuration for the single-server case.
- **Trade-off:** a lazy connection reports connection errors on first use rather than at boot.

## Alternatives Considered

- **A single global server**: the configuration before 0.13, replaced by named servers so
  one application can expose several, each with its own identity, transports and sessions.
- **A path default of `/mcp` for a single server**: not chosen because adding a second server
  would silently move the existing endpoint.

## References

- `src/McpBundle.php`: `configureServer()`, `configureRouting()`, `configureCommands()`,
  `configureClients()`, `httpPath()` and `assertServersAreIsolated()`.
- `src/Controller/McpController.php`, `src/Routing/RouteLoader.php`,
  `src/Http/MiddlewareFactory.php`: the HTTP transport.
- `src/Command/McpCommand.php`: the STDIO transport.
- `src/Client/ServerConnection.php`, `src/Client/McpClient.php`: client connections and their
  lifecycle.
- `CHANGELOG.md`: 0.13, multiple servers and the `clients:` configuration.
