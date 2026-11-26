CHANGELOG
=========

1.0
---

 * `McpToolbox::getTools()` carries a remote tool's `outputSchema` into `Tool::getMetadata()` under that key, readable through `Tool::getOutputSchema()`, instead of discarding it
 * Add typed tool effect hints populated from remote MCP annotations

0.15
----

 * `McpToolbox::getTools()` carries a remote tool's `readOnlyHint`, `destructiveHint`, `idempotentHint` and `openWorldHint` annotations into `Tool::getMetadata()`, keyed by their MCP spec field names, instead of discarding them
 * `McpToolbox` implements `ResetInterface`: `reset()` forgets the tool list, so the next listing asks the server again

0.14
----

 * Add the bridge
