CHANGELOG
=========

0.13
----

 * Add support for multiple MCP servers per application, configured under `servers:` — each with its own
   identity, transports, session store, HTTP route and set of exposed capabilities
 * Add per-server capability lists (`tools`, `prompts`, `resources`, `resource_templates`, `apps`) matching
   service ids, class names, namespace prefixes or `*`, replacing the implicit "every element on the one server"
 * Add `clients:` configuration to act as an MCP client: each named client owns a set of remote `servers:`
   reached over the stdio or HTTP transport
 * Add `Symfony\AI\McpBundle\Client\McpClientInterface` (service `mcp.client.<name>`) and
   `Symfony\AI\McpBundle\Client\ServerConnectionInterface` (service `mcp.client.<name>.server.<server>`),
   which own the connection lifecycle: connecting on first use and disconnecting on kernel reset
 * Add `mcp:client:debug` command to inspect the configured clients and what their remote servers advertise
 * Add a `--server` option to `debug:mcp` and a server argument to `mcp:server`
 * Add the `servers.<name>.lifecycle` option: `stateless` serves the 2026-07-28 revision (no `initialize`
   handshake, no session, HTTP only), `handshake` keeps the 2025-11-25-and-earlier behavior
 * Add `servers.<name>.protocol_versions` to declare the revisions a stateless server answers for
 * Add `servers.<name>.request_state` to sign the state a multi-round-trip answer carries through the client
 * Add `servers.<name>.cache` for the cache hints a stateless server puts on its answers, with per-method overrides
 * Add `servers.<name>.subscriptions` configuring delivery for `subscriptions/listen` streams
 * Add `servers.<name>.tasks` enabling the tasks extension (SEP-2663) with an in-memory or PSR-16 store
 * Add autoconfiguration for `Mcp\Capability\Completion\ProviderInterface` (tag `mcp.completion_provider`),
   so a completion provider is resolved from the container and can have constructor dependencies
 * Add the `clients.<name>.roots` option pointing at a `RootsCallbackInterface` service, and
   `getProtocolVersion()`, `complete()` and `sendRootsListChanged()` on `ServerConnectionInterface`

0.12
----

 * Register tools, prompts, resources, and resource templates via container instead of the SDK's file-based discovery
 * Add `debug:mcp` command listing the registered MCP capabilities with their handlers
 * Show MCP capabilities (including their handlers) in the profiler panel on every request, not only on requests serving MCP

0.11
----

 * Add `http.allowed_hosts` configuration to allow custom hosts or disable the DNS rebinding protection when exposing a public MCP server
 * Add MCP Apps support via the `#[AsMcpApp]`/`#[AsMcpAppTool]` attributes: interactive HTML UI resources
   whose tools return a context array the bundle renders server-side with Twig (HTML-over-the-wire);
   configurable via `mcp.apps.enabled`

0.8
---

 * Add `framework` session store backed by Symfony's `SessionHandlerInterface`

0.4
---

 * Add `ResetInterface` support to `TraceableRegistry` to clear collected data between requests

0.3
---

 * Add support for server description, icons, and website URL

0.1
---

 * Add the bundle
