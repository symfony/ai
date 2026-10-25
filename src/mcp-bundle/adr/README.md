# Architecture Decision Records

Architecture Decision Records of the MCP Bundle; the process and template live in the
repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-the-bundle-is-a-thin-integration-of-the-official-mcp-php-sdk.md) | **The bundle is a thin integration of the official MCP PHP SDK.** The protocol comes from `mcp/sdk`; the bundle only wires SDK objects as Symfony services. |
| [0002](0002-capabilities-are-container-services-compiled-into-the-server-builder.md) | **Capabilities are container services compiled into the server builder.** Attributed services are registered when the container is built, and each server's `registry` says what it exposes. |
| [0003](0003-named-isolated-servers-and-lazily-connected-clients.md) | **Named, isolated servers and lazily connected clients.** Each server has its own route or command and its own sessions; client connections open on first use and close on reset. |
