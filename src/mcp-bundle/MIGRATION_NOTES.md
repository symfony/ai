# Migration to External MCP SDK

This bundle has been updated to use the external `mcp/sdk` package instead of the internal `symfony/mcp-sdk`. 

## Current Status

✅ **Migration Complete**: The MCP Bundle has been successfully adapted to work with the external `mcp/sdk` package.

## Major API Changes

1. **Namespace changes**: `Symfony\AI\McpSdk` → `Mcp`
2. **Interface changes**: Many interfaces have been renamed or restructured
3. **Architecture changes**: The new SDK uses a unified `Registry` instead of separate tool executor/collection interfaces
4. **Handler changes**: Both notification and request handlers now implement `MethodHandlerInterface`
5. **Transport changes**: `StdioTransport` constructor expects raw resources instead of Symfony console I/O

## Implementation Details

The bundle has been completely adapted with the following changes:

1. ✅ **Service definitions updated**: Using new `Handler::make()` factory and `SymfonyRegistry`
2. ✅ **Registry system implemented**: Custom `SymfonyRegistry` bridges Symfony services with MCP Registry
3. ✅ **Transport integrations updated**: `StdioTransport` now uses raw resources (STDIN/STDOUT)
4. ✅ **Tests updated**: All tests passing with new architecture
5. ✅ **Demo tool updated**: Implements new `ToolExecutorInterface` with `CallToolRequest`/`CallToolResult`

## New Components

- **`SymfonyRegistry`**: Extends MCP Registry to work with Symfony services
- **`McpRegistryCompilerPass`**: Automatically registers tagged `mcp.tool` services
- **Updated tool interface**: Tools now implement `ToolExecutorInterface` from external SDK

## Test Status

✅ **All tests passing**: 10 tests, 24 assertions - the bundle is fully functional.