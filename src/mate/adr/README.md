# Mate ADRs

Architecture Decision Records of the Mate component; the process and template live in the
repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-mate-is-a-native-cli-not-an-mcp-server.md) | **Mate is a native CLI, not an MCP server.** Agents run `vendor/bin/mate` commands directly and read tool schemas on demand instead of connecting to a long-running MCP server. |
| [0002](0002-capabilities-are-attributed-methods-discovered-by-reflection.md) | **Capabilities are attributed methods discovered by reflection.** Methods marked `#[MateTool]`, `#[MateResource]` or `#[MateResourceTemplate]` are found by scanning directories and their classes are wired as autowired services. |
| [0003](0003-extensions-are-discovered-from-composer-extra-ai-mate.md) | **Extensions are discovered from Composer `extra.ai-mate`.** Packages declare scan directories, includes, instructions and skills in `composer.json`, and `mate/extensions.php` decides which ones are enabled. |
| [0004](0004-tool-input-schemas-come-from-signatures-and-phpdoc.md) | **Tool input schemas come from signatures and PHPDoc.** The JSON Schema and the argument casting both derive from the method signature and its `@param` tags, and unknown arguments are rejected. |
| [0005](0005-agent-instructions-are-generated-into-the-project.md) | **Agent instructions are generated into the project.** `discover` writes `mate/AGENT_INSTRUCTIONS.md` and managed blocks in `AGENTS.md` and `CLAUDE.md` so agents learn the tools and the right invocation. |
