---
status: accepted
date: 2026-10-25
---

# 0004. Tools are plain objects described by `#[AsTool]` behind a toolbox contract

## Context and Problem Statement

A tool needs two things: a definition the model understands (name, description, JSON
schema of its parameters) and code that runs when the model calls it. Tools come from
application code, from the tool bridges of this package (Brave, Wikipedia, Filesystem,
...) and from remote protocols such as MCP. Writing the JSON schema by hand would
duplicate the PHP signature, and a failing tool should not necessarily abort the
conversation.

## Decision

- `Toolbox\ToolboxInterface` is the contract the agent depends on: `getTools()` returns
  the `Platform\Tool\Tool` definitions and `execute(ToolCall)` returns a `ToolResult`.
- A tool is any object with an `#[AsTool(name, description, method = '__invoke')]`
  attribute, repeatable for several tools on one class. `Toolbox` builds the definitions
  with a `ToolFactoryInterface`: `ReflectionToolFactory` reads the attribute and derives
  the parameter schema from the method signature and docblock through the Platform's
  JSON schema `Factory`; `MemoryToolFactory` registers tools without attributes;
  `ChainFactory` combines factories.
- `AbstractToolbox::execute()` is final and holds the shared behavior: it dispatches
  `ToolCallRequested` (which can deny the call or provide a result), resolves arguments,
  collects sources for `HasSourcesInterface` tools, and dispatches `ToolCallSucceeded` or
  `ToolCallFailed`. Any other `\Throwable` is wrapped in
  `Toolbox\Exception\ToolExecutionException`. Subclasses implement only how a call turns
  into a value, which is how `Bridge\Mcp\McpToolbox` plugs in a remote protocol.
- `FaultTolerantToolbox` decorates a toolbox and turns a `ToolExecutionExceptionInterface`
  (via `getToolCallResult()`) or a `ToolNotFoundException` into a text result for the
  model. `ChainToolbox` offers several toolboxes as one and refuses duplicate names.

Tool bridges are therefore ordinary `#[AsTool]` classes in their own packages under
`src/Bridge/`, with no registration API of their own.

## Consequences

- **Good:** the PHP signature is the single source of the schema; a tool is testable
  without the agent.
- **Good:** policy (approval, caching, validation through
  `ValidateToolCallArgumentsListener`) is added with event listeners, not subclasses.
- **Trade-off:** schema quality depends on precise type hints and docblocks.
- **Trade-off:** errors abort the run unless the toolbox is wrapped in
  `FaultTolerantToolbox`, and the message sent to the model is deliberately generic.

## Alternatives Considered

- **Hand-written schema per tool**: not chosen because it drifts from the code.
- **An interface every tool implements**: not chosen because one class may expose several
  methods as tools and existing services can become tools by adding an attribute.

## References

- `src/Toolbox/ToolboxInterface.php`, `src/Toolbox/AbstractToolbox.php`, `src/Toolbox/Toolbox.php`.
- `src/Toolbox/Attribute/AsTool.php`, `src/Toolbox/ToolFactory/ReflectionToolFactory.php`.
- `src/Toolbox/FaultTolerantToolbox.php`, `src/Toolbox/ChainToolbox.php`.
- `src/Toolbox/Event/ToolCallRequested.php`: deny or short-circuit a call.
- `src/Toolbox/Exception/ToolExecutionExceptionInterface.php`: the model-facing error.
- `src/Bridge/Brave/Brave.php`, `src/Bridge/Mcp/McpToolbox.php`: how bridges plug in.
