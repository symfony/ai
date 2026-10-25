---
status: accepted
date: 2026-10-25
---

# 0005. Agents compose through `AgentInterface`

## Context and Problem Statement

Applications combine agents: a router that hands a request to a specialist, an agent
that consults another agent as a tool, or an agent wrapped with speech or profiling. A
dedicated workflow engine or graph API would be a second programming model next to the
agent and would need its own streaming, cancellation and tracing.

## Decision

Combinations are built from the same `AgentInterface` (`call()` and `getName()`):

- `MultiAgent\MultiAgent` implements `AgentInterface`. It asks an orchestrator agent for a
  structured `MultiAgent\Handoff\Decision` (via `response_format`), matches the chosen
  name against its `MultiAgent\Handoff` definitions (target agent plus `when` keywords),
  and delegates the original user message to that agent, or to a fallback agent when no
  agent is chosen or the name is unknown. The routing is reported as a
  `Progress::STAGE_HANDOFF` update, and the progress of the delegated execution is
  forwarded, without the deltas of the selection round.
- `Toolbox\Tool\Subagent` exposes any agent as a tool, passing on its sources.
- Decorators add behavior around an inner agent: `SpeechAgent` (speech-to-text and
  text-to-speech) and `TraceableAgent` (profiling).
- `getName()` is the identifier used for routing and debugging.

## Consequences

- **Good:** a multi-agent system can be used wherever an agent is expected, including in
  another `MultiAgent` or behind a `Chat`.
- **Good:** progress, cancellation and tracing work across nesting because every level
  returns an `Execution`.
- **Trade-off:** routing costs an extra model request per call, and its quality depends
  on the orchestrator and on the `when` keywords.
- **Trade-off:** the chosen specialist only receives the user message, not the full
  conversation; only the fallback and orchestrator paths get the whole `MessageBag`.

## Alternatives Considered

- **A graph or workflow API**: not chosen because handoff and agent-as-tool cover the
  common cases with the existing contract.
- **Routing by keyword matching in PHP**: not used; the `when` keywords are rendered as
  capability descriptions into the orchestrator's selection prompt, so the model decides
  and phrasing that does not match a keyword literally can still be routed.

## References

- `src/AgentInterface.php`: the shared contract.
- `src/MultiAgent/MultiAgent.php`, `src/MultiAgent/Handoff.php`,
  `src/MultiAgent/Handoff/Decision.php`: orchestration and handoff.
- `src/Toolbox/Tool/Subagent.php`: an agent as a tool.
- `src/SpeechAgent.php`, `src/TraceableAgent.php`: decorators.
