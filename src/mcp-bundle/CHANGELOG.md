CHANGELOG
=========

0.7
---

 * Add PSR-15 HTTP middleware pipeline support (`mcp.middleware` tagged services)
 * Add configurable additional routes for OAuth well-known endpoints
 * Add OAuth integration with OIDC discovery, JWT validation, and client registration
 * Add `SymfonySecurityMiddleware` to bridge OAuth JWT claims to Symfony security tokens
 * Add `IsGrantedChecker` for `#[IsGranted]` attribute-based tool authorization
 * Add `SecurityReferenceHandler` to enforce access control on tool execution
 * Add `FilteredListToolsHandler` to filter `tools/list` by user grants
 * Add `TestSecurityMiddleware` for testing with `X-Test-Roles` header (opt-in)
 * Add `FrameworkSessionStore` with application-level TTL via JSON envelope
 * Add `reference_handler` configuration option
 * Add `security_middleware` configuration option

0.4
---

 * Add `ResetInterface` support to `TraceableRegistry` to clear collected data between requests

0.3
---

 * Add support for server description, icons, and website URL

0.1
---

 * Add the bundle
