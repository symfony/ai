# Architecture Decision Records

Architecture Decision Records of the AI Bundle; the process and template live in the
repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-one-semantic-configuration-tree-with-optional-bridge-packages.md) | **One semantic configuration tree with optional bridge packages.** All platforms, stores, agents and related services are configured under `ai`, assembled from per-bridge fragments in `config/`, and each bridge is wired only when its optional package is installed. |
| [0002](0002-predictable-service-ids-and-named-autowiring-aliases.md) | **Predictable service ids and named autowiring aliases.** Every configured instance gets an id derived from its key and a named autowiring alias on its interface, and the interface itself is aliased when only one instance exists. |
| [0003](0003-tools-and-processors-are-autoconfigured-services-bound-to-agents-at-compile-time.md) | **Tools and processors are autoconfigured services bound to agents at compile time.** Attributes and interfaces tag tools and processors, tools are opt-in per agent, and `ProcessorCompilerPass` attaches processors to agents by name and priority. |
| [0004](0004-tool-authorization-with-isgrantedtool-on-the-tool-call-event.md) | **Tool authorization with #[IsGrantedTool] on the tool call event.** A listener on `ToolCallArgumentsResolved` checks `#[IsGrantedTool]` against Symfony Security with the model's resolved arguments as subject. |
| [0005](0005-profiling-through-traceable-decorators-in-debug-mode.md) | **Profiling through traceable decorators in debug mode.** `DebugCompilerPass` wraps every tagged platform, agent, toolbox and store in a traceable decorator that feeds the profiler, the timeline and test assertions, only when `kernel.debug` is on. |
