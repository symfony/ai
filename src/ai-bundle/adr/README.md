# Architecture Decision Records

Architecture Decision Records of the AI Bundle; the process and template live in the
repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-one-semantic-configuration-tree-with-optional-bridge-packages.md) | **One semantic configuration tree with optional bridge packages.** Everything is configured under `ai`, and a bridge is only wired when its package is installed. |
| [0002](0002-predictable-service-ids-and-named-autowiring-aliases.md) | **Predictable service ids and named autowiring aliases.** Each instance gets an id from its config key and a named alias, so code can pick it with `#[Target]`. |
| [0003](0003-tools-and-processors-are-autoconfigured-services-bound-to-agents-at-compile-time.md) | **Tools and processors are autoconfigured services bound to agents at compile time.** Attributes register tools and processors, tools are opt-in per agent, and processors are attached by agent and priority. |
| [0004](0004-tool-authorization-with-isgrantedtool-on-the-tool-call-event.md) | **Tool authorization with #[IsGrantedTool] on the tool call event.** A listener checks Symfony Security before a tool runs, using the model's arguments as subject. |
| [0005](0005-profiling-through-traceable-decorators-in-debug-mode.md) | **Profiling through traceable decorators in debug mode.** In debug mode, services are wrapped in decorators that record calls for the profiler and for tests. |
