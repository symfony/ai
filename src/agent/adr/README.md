# Agent ADRs

Architecture Decision Records of the Agent component; the process and template live in
the repository's root [`adr/README.md`](../../../adr/README.md).

| ADR | Decision |
| --- | -------- |
| [0001](0001-input-and-output-processors-around-the-platform-call.md) | **Input and output processors around the platform call.** Cross-cutting behavior such as system prompts, model overrides and memory hooks in as ordered `InputProcessorInterface`/`OutputProcessorInterface` chains that mutate `Input` and `Output` |
| [0002](0002-agent-call-returns-a-lazy-execution.md) | **An agent call returns a lazy execution that is also its result.** `AgentInterface::call()` returns an `Execution` that can be read eagerly, iterated for `Progress` updates, streamed or canceled |
| [0003](0003-the-agent-drives-the-tool-calling-loop.md) | **The agent drives the tool-calling loop through a replaceable executor.** The internal `Runner` loops over model requests and tool rounds, bounded by `maxToolCalls`, and delegates running the calls to a `ToolExecutorInterface` |
| [0004](0004-tools-are-plain-objects-described-by-as-tool.md) | **Tools are plain objects described by `#[AsTool]` behind a toolbox contract.** Tool definitions are derived by reflection, execution is shared in `AbstractToolbox` with events, and `FaultTolerantToolbox` turns failures into messages for the model |
| [0005](0005-agents-compose-through-agent-interface.md) | **Agents compose through `AgentInterface`.** `MultiAgent`, `Subagent`, `SpeechAgent` and `TraceableAgent` combine agents with the same contract instead of a separate workflow API |
