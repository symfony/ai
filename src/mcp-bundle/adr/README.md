# Architecture Decision Records

Architecture Decision Records of the MCP Bundle; the process and template live in the
repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-the-bundle-is-a-thin-integration-of-the-official-mcp-php-sdk.md) | **The bundle is a thin integration of the official MCP PHP SDK.** Protocol, transports and sessions come from `mcp/sdk`; the bundle only builds and wires SDK objects and adds Symfony glue. |
| [0002](0002-capabilities-are-container-services-compiled-into-the-server-builder.md) | **Capabilities are container services compiled into the server builder.** SDK attributes are autoconfigured, `McpPass` compiles their metadata into each server builder, and a required `registry` decides which server exposes which element. |
| [0003](0003-named-isolated-servers-and-lazily-connected-clients.md) | **Named, isolated servers and lazily connected clients.** Each named server gets its own route or STDIO command and session storage, and each client connection connects on first use and disconnects on kernel reset. |
