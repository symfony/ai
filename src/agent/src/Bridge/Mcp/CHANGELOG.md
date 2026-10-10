CHANGELOG
=========

0.15
----

 * `McpToolbox::getTools()` carries a remote tool's `readOnlyHint`, `destructiveHint`, `idempotentHint` and `openWorldHint` annotations into `Tool::getMetadata()`, keyed by their MCP spec field names, instead of discarding them
 * `McpToolbox` implements `ResetInterface`: `reset()` forgets the tool list, so the next listing asks the server again

0.14
----

 * Add the bridge
