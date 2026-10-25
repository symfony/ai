---
status: accepted
date: 2026-10-25
---

# 0005. Agents compose through `AgentInterface`

## Context and Problem Statement

Applications combine agents: a router that hands a request to a specialist, an agent
that asks another agent as a tool, or an agent wrapped with speech or profiling. A
separate workflow engine or graph API would be a second programming model next to the
agent, with its own streaming, cancellation and tracing.

## Decision

Combinations use the same `AgentInterface` (`call()` and `getName()`):

- `MultiAgent\MultiAgent` implements `AgentInterface`. It asks an orchestrator agent for
  a structured `MultiAgent\Handoff\Decision` (via `response_format`), matches the chosen
  name against its `MultiAgent\Handoff` definitions (target agent plus `when` keywords),
  and passes the original user message to that agent. When no agent is chosen or the
  name is unknown, a fallback agent answers. The routing is reported as a
  `Progress::STAGE_HANDOFF` update, and the progress of the chosen agent is passed on,
  without the text pieces of the selection round.
- `Toolbox\Tool\Subagent` offers any agent as a tool and passes on its sources.
- Decorators add behavior around an inner agent: `SpeechAgent` (speech-to-text and
  text-to-speech) and `TraceableAgent` (profiling).
- `getName()` is the identifier used for routing and debugging.

## Consequences

- **Good:** a multi-agent setup can be used wherever an agent is expected, including
  inside another `MultiAgent` or behind a `Chat`.
- **Good:** progress, cancellation and tracing work across nesting, because every level
  returns an `Execution`.
- **Trade-off:** routing costs one extra model request per call, and its quality depends
  on the orchestrator and on the `when` keywords.
- **Trade-off:** the chosen specialist only gets the user message, not the whole
  conversation; only the fallback and the orchestrator get the full `MessageBag`.

## Alternatives Considered

- **A graph or workflow API**: not built; handoff and agent-as-tool cover the common
  cases with the existing contract.
- **Routing by keyword matching in PHP**: not used. The `when` keywords are written into
  the orchestrator's prompt, so the model decides, and wording that does not match a
  keyword exactly can still be routed.

## History

- 2025-03-20, php-llm/llm-chain#266 by Christopher Hertel: added a tool that wraps a chain, the origin of `Subagent`.
- 2025-10-01, #517 by @OskarStark (Oskar Stark): added `MultiAgent` with an orchestrator and handoffs.
- 2026-02-04, #1547 by @chr-hertel (Christopher Hertel): renamed the agent-as-tool to `Subagent`.
- 2026-02-21, #1656 by @Guikingone (Guillaume Loulier): added `TraceableAgent` for the profiler.
- 2026-04-05, #943 by @Guikingone (Guillaume Loulier) and @chr-hertel: added speech support with `SpeechAgent`.
- 2026-09-05, #2483 by @saifulferoz: `MultiAgent` and `SpeechAgent` forward progress from the agent they delegate to.

## References

- `src/AgentInterface.php`: the shared contract.
- `src/MultiAgent/MultiAgent.php`, `src/MultiAgent/Handoff.php`,
  `src/MultiAgent/Handoff/Decision.php`: orchestration and handoff.
- `src/Toolbox/Tool/Subagent.php`: an agent as a tool.
- `src/SpeechAgent.php`, `src/TraceableAgent.php`: decorators.
