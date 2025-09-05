# MCP Bundle - Future Improvements

## Potential Improvements

1. **🔧 Enhanced Tool Registration**
   - Consider supporting the new `#[McpTool]` attribute for automatic tool discovery
   - The external SDK provides attribute-based tool registration that could supplement the service-based approach

2. **📝 Documentation Updates**
   - Update bundle documentation to reflect the new `ToolExecutorInterface` requirement
   - Add examples of creating tools with the new SDK interfaces
   - Document migration path for users upgrading from old internal SDK

3. **🧪 Extended Test Coverage**
   - Add integration tests with actual MCP client communication
   - Test error handling scenarios with malformed requests
   - Test tool registration with various interface combinations

4. **⚡ Performance Optimizations**
   - The current Registry wrapper creates callables for each tool - could be optimized
   - Consider lazy loading of tool services for large applications

5. **🔄 Additional Capabilities**
   - Support for Prompts and Resources (currently only Tools are implemented)
   - Integration with the Discovery system from external SDK
   - Support for server capabilities customization

## Breaking Changes from Previous Version

- **Tools must implement `ToolExecutorInterface`** from `mcp/sdk` instead of old interfaces
- **Method signature change**: `call(CallToolRequest)` → `CallToolResult` instead of old signatures
- **Return format change**: Must return `CallToolResult` with array of `Content` objects
- **No backward compatibility**: Old tool interfaces are not supported