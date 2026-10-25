---
status: accepted
date: 2026-10-25
---

# 0004. Tools are plain objects described by `#[AsTool]` behind a toolbox contract

## Context and Problem Statement

A tool needs two things: a definition the model understands (name, description, JSON
schema of its parameters) and code that runs when the model calls it. Tools come from
application code, from the tool bridges of this package (Brave, Wikipedia, Filesystem,
...) and from remote protocols such as MCP. Writing the JSON schema by hand would repeat
the PHP signature, and a failing tool should not always stop the conversation.

## Decision

- `Toolbox\ToolboxInterface` is the contract the agent depends on: `getTools()` returns
  the `Platform\Tool\Tool` definitions and `execute(ToolCall)` returns a `ToolResult`.
- A tool is any object with an `#[AsTool(name, description, method = '__invoke')]`
  attribute. The attribute can repeat, so one class can offer several tools. `Toolbox`
  builds the definitions with a `ToolFactoryInterface`: `ReflectionToolFactory` reads the
  attribute and derives the parameter schema from the method signature and docblock
  through the Platform's JSON schema `Factory`; `MemoryToolFactory` registers tools
  without attributes; `ChainFactory` combines factories.
- `AbstractToolbox::execute()` is final and holds the shared behavior. It dispatches
  `ToolCallRequested` (which can deny the call or provide a result), resolves arguments,
  collects sources of `HasSourcesInterface` tools, and dispatches `ToolCallSucceeded` or
  `ToolCallFailed`. Any other `\Throwable` is wrapped in
  `Toolbox\Exception\ToolExecutionException`. Subclasses only say how a call turns into
  a value. This is how `Bridge\Mcp\McpToolbox` plugs in a remote MCP server, whose
  schema comes from the server instead of a PHP signature.
- `FaultTolerantToolbox` wraps a toolbox and turns a `ToolExecutionExceptionInterface`
  (via `getToolCallResult()`) or a `ToolNotFoundException` into a text result for the
  model, so the model can react, for example by fixing invalid arguments. `ChainToolbox`
  offers several toolboxes as one and refuses duplicate tool names.

Tool bridges are ordinary `#[AsTool]` classes in their own packages under
`src/Bridge/`, with no registration API of their own.

## Consequences

- **Good:** the PHP signature is the single source of the schema; a tool can be tested
  without the agent.
- **Good:** policy (human approval, caching, argument validation through
  `ValidateToolCallArgumentsListener`) is added with event listeners, not subclasses.
- **Trade-off:** schema quality depends on precise type hints and docblocks.
- **Trade-off:** errors stop the run unless the toolbox is wrapped in
  `FaultTolerantToolbox`, and the message sent to the model is generic unless the
  exception implements `ToolExecutionExceptionInterface`.

## Alternatives Considered

- **Hand-written schema per tool**: not chosen; the attribute plus reflection has been
  the design since the first commit.
- **An interface every tool implements**: not chosen; one class may offer several
  methods as tools, and an existing service becomes a tool by adding an attribute.
- **An MCP server as one tool inside a toolbox**: rejected in #2497; a server that
  answers `tools/list` and `tools/call` already is a toolbox.

## History

- 2023-11-04, 4804cf0 by Christopher Hertel: the first commit of LlmChain shipped `#[AsTool]` with a parameter analyzer that reads the method signature.
- 2025-02-21, php-llm/llm-chain#213 by Christopher Hertel: added the optional `FaultTolerantToolbox`.
- 2025-09-09, #478 by @valtzu: `ToolExecutionExceptionInterface` lets a tool show its error to the model so it can fix its input.
- 2025-10-03, #709 by @franzwilding (Franz Wilding): dispatch more tool call events, such as success and failure.
- 2026-03-17, #1768 by @chr-hertel (Christopher Hertel): `ToolCallRequested` event, a small building block for human-in-the-loop approval.
- 2026-09-16, #2497 by @chr-hertel (Christopher Hertel): extracted `AbstractToolbox`, added `ChainToolbox` and the MCP client bridge.

## References

- `src/Toolbox/ToolboxInterface.php`, `src/Toolbox/AbstractToolbox.php`, `src/Toolbox/Toolbox.php`.
- `src/Toolbox/Attribute/AsTool.php`, `src/Toolbox/ToolFactory/ReflectionToolFactory.php`.
- `src/Toolbox/FaultTolerantToolbox.php`, `src/Toolbox/ChainToolbox.php`.
- `src/Toolbox/Event/ToolCallRequested.php`: deny or short-circuit a call.
- `src/Toolbox/Exception/ToolExecutionExceptionInterface.php`: the model-facing error.
- `src/Bridge/Brave/Brave.php`, `src/Bridge/Mcp/McpToolbox.php`: how bridges plug in.
