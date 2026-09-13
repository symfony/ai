CHANGELOG
=========

0.14
----

 * Add an `Arguments` column to `tools:list`'s table output, and stop truncating tool descriptions to 50 characters, so a tool's parameters and full description are visible without a separate `tools:inspect` call
 * Add a `tools:inspect <tool-name>` hint to `tools:call`'s error output when a parameter name is unknown or a required one is missing

0.13
----

 * Add `Encoding\ResponseEncoder::encodeUntrusted()`, wrapping a payload under an `untrusted_data` key alongside a `_security_notice`, so tool responses carrying data captured from the inspected application are explicitly marked as data rather than instructions
 * Change skill installation to copy skills into `.agents/skills/mate-<name>/` (rewriting the frontmatter name to the installed name) with relative `.claude/skills/` mirror symlinks, instead of symlinking into `vendor/`; the mirror falls back to a copy where symlinks are unavailable
 * Change `skills:install` into an idempotent reconciler that rebuilds both generated folders from source or user override on every run and prunes skills of removed or disabled extensions; `discover` runs it automatically
 * Add all per-skill state to `mate/extensions.php`: the user-editable `enabled` and `mode` (`managed`|`override`) plus the machine-managed `state`, `source`, `source_hash`, `hash` and `targets`
 * Add `skills:list` command: a read-only diagnostic listing declared and installed skills with their enabled/mode/state/status (including stale and broken detection)
 * Add `skills:validate` command: checks the generated folders against the recorded state and fails on hand-edited content, missing folders or a mispointed mirror (`--strict` also fails on warnings)
 * Add `skills:prune` command: removes generated `mate-*` folders that no longer belong to any skill (`--dry-run` to preview)
 * Add `skills:override` and `skills:reset` commands: take ownership of a skill by copying it into `mate/skills/<name>/`, and hand it back to Mate again
 * Add `--dry-run` to `skills:install`, reporting what the run would install, rebuild or remove without writing anything
 * Add content checks to `skills:validate`: a warning when SKILL.md links to a file that is not part of the installed skill (failing `--strict` like any other warning), and suggestions when a description is shorter than 40 characters or never says when the skill applies (printed only, never changing the exit code, not even with `--strict`)
 * Add `skills:disable` and `skills:enable` commands: flip `enabled` for a single skill and rebuild or remove its generated folders
 * Add a managed `CLAUDE.md` in the project root to `mate init`/`mate discover` that imports `AGENTS.md` via `@AGENTS.md`, so Claude Code discovers the Mate CLI instructions it would otherwise never read
 * Replace the MCP server with a native CLI: Mate no longer depends on `mcp/sdk`, the `serve` and `stop` commands and the MCP runtime are gone, and `mate init` writes CLI-oriented agent instructions instead of `mcp.json`/`.mcp.json` and the Codex wrappers. Tools and resources are discovered by reflection from the native `#[MateTool]`, `#[MateResource]` and `#[MateResourceTemplate]` attributes in `Symfony\AI\Mate\Attribute`; prompts have no native equivalent and are removed along with `debug:capabilities --type=prompt`
 * Rename the tool/resource commands from `mcp:tools:*`/`mcp:resources:read` to `tools:list`, `tools:inspect`, `tools:call` and `resources:read`, and change `tools:call` to take tool parameters as long options (`tools:call symfony-profiler-list --limit=1`, repeated for variadic parameters) with `--json` for nested values, replacing the positional JSON argument
 * Add `mate.invocation` and `mate.php_version` to `mate/config.php`: `mate init` asks how the coding agent should invoke Mate (defaulting to `ddev exec vendor/bin/mate` when a `.ddev/` directory is present), materializes that command into the agent instructions, pins `mate.php_version` by running `php` through that wrapper (falling back to the current process and warning when it cannot be probed), and Mate refuses to start under a different PHP major.minor (`init`, `discover`, `list`, `help` and `completion` warn instead of refusing)

0.12
----

 * Add `skills:install` command and `extra.ai-mate.skills` config key to install Agent Skills shipped by extensions into `.agents/skills` and `.claude/skills` for coding agents

0.11
----

 * Add PHP binary prompt to `mate init` (detects `.ddev/` and defaults the generated `mcp.json` launch command to `ddev exec php`, otherwise `php`) so the MCP server can be started from the host for containerized setups

0.9
---

 * Add `tag` filter parameter to `symfony-services` MCP tool to filter services by DI tag name (e.g. `kernel.event_listener`, `twig.extension`)
 * Add `channel` filter parameter to `monolog-tail` MCP tool for consistency with `monolog-search`
 * Add `TimeCollectorFormatter` for the Symfony profiler `time` collector, exposing request duration, initialization time, and stopwatch events sorted by duration
 * Add `LoggerCollectorFormatter` for the Symfony profiler `logger` collector, exposing error/warning/deprecation/scream counts and individual log entries
 * Add `MemoryCollectorFormatter` for the Symfony profiler `memory` collector, exposing peak memory usage, memory limit, and usage percentage
 * Add `symfony-service-detail` MCP tool to retrieve full details of a single DI container service by its exact ID (class, tags, method calls, factory)
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
