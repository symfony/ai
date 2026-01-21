CHANGELOG
=========

0.3
---

 * Add support for `instructions` field in extension composer.json to provide AI agent guidance
 * Add support for `extension: false` flag in `extra.ai-mate` composer.json configuration to exclude packages from being discovered as extensions
 * Add `ToolsInspectCommand` to inspect a specific tool
 * Add `ToolsListCommand` to list all available tools
 * Add `ToolsCallCommand` to call a specific tool with input

0.2
---

 * Add `StopCommand` to stop a running server
 * Add `--force-keep-alive` option to `ServeCommand` to restart server if it was stopped
 * Add `debug:capabilities` command to display all discovered MCP capabilities grouped by extension

0.1
---

 * Add component
