# Migration to External MCP SDK

This bundle has been updated to use the external `mcp/sdk` package instead of the internal `symfony/mcp-sdk`. 

## Current Status

⚠️ **Work in Progress**: The external MCP SDK is still in active development and the API has changed significantly from the original internal SDK.

## Major API Changes

1. **Namespace changes**: `Symfony\AI\McpSdk` → `Mcp`
2. **Interface changes**: Many interfaces have been renamed or restructured
3. **Architecture changes**: The new SDK uses a unified `Registry` instead of separate tool executor/collection interfaces
4. **Handler changes**: Both notification and request handlers now implement `MethodHandlerInterface`
5. **Transport changes**: `StdioTransport` constructor expects raw resources instead of Symfony console I/O

## Required Work

The bundle currently has the namespace imports updated but the service definitions and architecture need to be fully adapted to work with the new SDK structure. This includes:

1. Updating service definitions in `config/services.php`
2. Adapting the bundle to use the new `Registry` system
3. Updating transport integrations
4. Fixing test assertions
5. Updating the demo application tool example

## Current Test Status

Several tests are currently failing due to the namespace and class name changes. These need to be updated once the service architecture is adapted.