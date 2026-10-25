---
status: accepted
date: 2026-10-25
---

# 0003. Tools and processors are autoconfigured services bound to agents at compile time

## Context and Problem Statement

An agent is extended in two ways: tools the model may call and input/output processors that
run around each call. Both are ordinary application classes with their own dependencies, so
they belong in the service container. The bundle needs to discover them and attach each one
to the right agent in the right order, without the application wiring every agent by hand.

## Decision

The bundle relies on Symfony autoconfiguration. In `AiBundle::loadExtension()` it registers:

- `#[AsTool]` (Agent component) to tag a service `ai.tool` with its name, description and
  method;
- `#[AsInputProcessor]` and `#[AsOutputProcessor]` to tag a service
  `ai.agent.input_processor` / `ai.agent.output_processor` with an optional `agent` and a
  `priority`;
- `InputProcessorInterface` and `OutputProcessorInterface` to add the same tags with
  `tagged_by: interface`.

Tools are opt-in per agent. `ai.agent.<name>.tools` defaults to disabled; `tools: true`
gives the agent's toolbox (`ai.toolbox.<name>`, a child of `ai.toolbox.abstract`) every
service tagged `ai.tool`, and an explicit list restricts it to the named services, agents
(wrapped in `Subagent`) or MCP servers.

Processors are attached by `ProcessorCompilerPass`. For every service tagged `ai.agent` it
collects the processors whose `agent` is unset or equals the agent's service id, sorts them
by descending priority and sets the `$inputProcessors` and `$outputProcessors` arguments.
An interface tag is skipped when the same service also carries an attribute tag, so the
attribute's agent and priority win. Processors the bundle creates itself, such as the
system prompt (`-30`) and memory (`-40`) processors, use the same tags.

## Consequences

- **Good:** a tool or processor is a class with an attribute; no YAML is needed to register
  it, and it gets constructor injection like any other service.
- **Good:** priorities give a deterministic order across bundle and application processors.
- **Trade-off:** a processor without an `agent` applies to every agent, which is easy to
  overlook when a second agent is added.
- **Trade-off:** tool metadata is read by reflection (`ReflectionToolFactory`), so it is only
  validated when a toolbox is built, apart from schema providers checked by
  `SchemaProviderValidationPass`.

## Alternatives Considered

- **Listing every tool and processor in the agent configuration**: still possible for tools,
  but not the only way, because it duplicates what the class already declares.
- **Injecting all tagged tools into every agent by default**: the behavior before 0.10;
  replaced so that an agent only receives tools when its configuration asks for them.

## References

- `src/AiBundle.php`: `registerAttributeForAutoconfiguration()` calls and
  `processAgentConfig()` (toolbox, system prompt and memory processors).
- `src/DependencyInjection/ProcessorCompilerPass.php`: per-agent processor binding and order.
- `src/DependencyInjection/SchemaProviderValidationPass.php`: build-time schema provider check.
- `config/options.php`: the `tools` option and its opt-in normalization.
- `config/services.php`: `ai.toolbox.abstract` with `tagged_iterator('ai.tool')`.
- `CHANGELOG.md`: 0.10, tools become opt-in.
