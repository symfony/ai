---
status: accepted
date: 2026-10-25
---

# 0003. Named, isolated servers and lazily connected clients

## Context and Problem Statement

An application may run several MCP servers, for example a public one and an internal one,
and may call remote MCP servers itself. Each server needs its own name, transports and
sessions, and must not accept another server's sessions. A client connection holds a child
process or an HTTP session that must not leak from one request to the next.

## Decision

Servers are configured under `mcp.servers.<name>` and clients under `mcp.clients.<name>`.

Each server gets its own services and can use two transports:

- **HTTP** (on by default): one route per server, at `/mcp/<name>` unless `http.path` is
  set. `McpController` passes the request to the SDK. The SDK's DNS rebinding protection
  stays on and can be adjusted with `http.allowed_hosts` and `http.allowed_origins`.
- **STDIO** (off by default): `mcp:server <name>` runs one server per process.

The bundle fails the build if two servers share an HTTP path or a session storage, because
the SDK does not separate session ids by server: a session from a public server would
otherwise be accepted by a protected one.

For each remote server, the bundle creates a `ServerConnection`. It connects on the first
call and disconnects on `kernel.reset`, so long-running workers start each message clean.

## Consequences

- **Good:** servers for different audiences can live in one application, each behind its own
  route and firewall.
- **Good:** application code calls remote servers without managing connections. The AI
  Bundle uses these connections for its `mcp_server` tools.
- **Trade-off:** a connection error shows up on first use, not when the application starts.

## Alternatives Considered

- **One global server**: the setup before 0.13, at `/_mcp`, with its transports under the
  misleading key `client_transports`. Replaced by named servers and clients in #2412.
- **`/mcp` as the path for a single server**: not chosen, because adding a second server
  would move the first one's endpoint.

## History

- 2025-03-30, php-llm/mcp-bundle#1 by Christopher Hertel: STDIO and SSE transports for the
  single server, configured under `client_transports`.
- 2026-03-04, #1720 by @soyuka: responses are streamed when the client uses SSE.
- 2026-07-11, #2250 by @Amoifr (Pascal Cescon): configurable allowed hosts for the DNS
  rebinding protection, because the SDK default only accepts `localhost`, which blocks
  public servers.
- 2026-08-22, #2412 by @chr-hertel (Christopher Hertel): named servers and clients, separate
  sessions per server, paths derived from the name, and connections that open on first use
  and close on `kernel.reset`.
- 2026-09-16, #2497 by @chr-hertel (Christopher Hertel): the Agent MCP client bridge, which
  the AI Bundle's `mcp_server` tools use on top of these connections.

## References

- `src/McpBundle.php`: `configureServer()`, `configureClients()`, `assertServersAreIsolated()`.
- `src/Controller/McpController.php`, `src/Routing/RouteLoader.php`: the HTTP transport.
- `src/Command/McpCommand.php`: the STDIO transport.
- `src/Client/ServerConnection.php`: the client connection lifecycle.
