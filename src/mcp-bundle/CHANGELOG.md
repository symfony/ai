CHANGELOG
=========

0.12
----

 * Register tools, prompts, resources, and resource templates via container instead of the SDK's file-based discovery

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
