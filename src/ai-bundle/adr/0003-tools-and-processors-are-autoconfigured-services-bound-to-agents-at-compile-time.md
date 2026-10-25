---
status: accepted
date: 2026-10-25
---

# 0003. Tools and processors are autoconfigured services bound to agents at compile time

## Context and Problem Statement

Agents are extended with tools, which the model can call, and with input and output
processors, which run before and after each call. Both are normal application classes with
their own dependencies. The bundle must find them and give each one to the right agent, in
the right order, without manual wiring.

## Decision

The bundle uses Symfony autoconfiguration:

- `#[AsTool]` tags a service `ai.tool`.
- `#[AsInputProcessor]` and `#[AsOutputProcessor]` tag a service as a processor, with an
  optional `agent` and a `priority`.
- Classes implementing `InputProcessorInterface` or `OutputProcessorInterface` get the same
  tags automatically.

Tools are opt-in for each agent. Without a `tools` option an agent has no tools.
`tools: true` gives it every `ai.tool` service, and a list gives it only the listed ones.

`ProcessorCompilerPass` gives each agent the processors meant for it (or for all agents),
sorted by priority, highest first. If a class has both an attribute and an interface, the
attribute wins.

## Consequences

- **Good:** adding a tool or processor means writing a class with an attribute; no YAML.
- **Good:** the order of processors is predictable.
- **Trade-off:** a processor without an `agent` runs for every agent, which is easy to miss
  when a second agent is added.

## Alternatives Considered

- **Giving every agent all tagged tools**: the behavior before 0.10. Replaced, so an agent
  only gets tools when its configuration asks for them.

## References

- `src/AiBundle.php`: the attribute registration and `processAgentConfig()`.
- `src/DependencyInjection/ProcessorCompilerPass.php`: processor binding and order.
- `config/options.php`: the `tools` option.
- `CHANGELOG.md`: 0.10, tools become opt-in.
