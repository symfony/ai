CHANGELOG
=========

0.9
---

 * Add `tag` filter parameter to `symfony-services` MCP tool to filter services by DI tag name (e.g. `kernel.event_listener`, `twig.extension`)
 * Add `ResourcesReadCommand` (`mcp:resources:read`) to read MCP resources by URI from the CLI
 * Change default user namespace scaffolded by `mate init` from `App\Mate\` to `Mate\`
 * Allow Symfony profiler capabilities (`ProfilerResourceTemplate` and `ProfilerTool`) to be instantiated without a `ProfilerDataProvider`, throwing a clear `RuntimeException` when invoked in workspaces without profiler support
 * Add `--ignore-missing-file` option to the `discover` command that exits successfully without doing any work when `mate/extensions.php` does not exist (intended for unconditional invocation from Composer scripts wired by the Symfony Flex recipe)
 * Make `json-input` argument optional in `mcp:tools:call` command (defaults to `{}`)

0.7
---

 * Add TOON format (requires `helgesverre/toon`) to `mcp:tools:list`, `mcp:tools:inspect`, `mcp:tools:call`, `debug:capabilities`, `debug:extensions` to allow token efficient usage in CLI
 * Add raw data fallback for profiler collectors without a registered formatter
 * Add Codex wrapper generation (`bin/codex`, `bin/codex.bat`) to `mate init`
 * Add AGENT instruction artifact materialization to `mate discover` (`mate/AGENT_INSTRUCTIONS.md` and managed `AGENTS.md` block)
 * Merge `php-version`, `operating-system`, `operating-system-family`, and `php-extensions` tools into a single `server-info` tool
 * Add optional TOON format encoding for MCP tool responses to reduce token consumption (install `helgesverre/toon` to enable)

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
