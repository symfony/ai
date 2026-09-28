CHANGELOG
=========

0.14
----

 * Add the bridge
 * `McpToolbox::getTools()` carries a remote tool's `readOnlyHint`, `destructiveHint`, `idempotentHint` and `openWorldHint` annotations into `Tool::getMetadata()`, keyed by their MCP spec field names, instead of discarding them
