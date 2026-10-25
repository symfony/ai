---
status: accepted
date: 2026-10-25
---

# 0004. Tool authorization with #[IsGrantedTool] on the tool call event

## Context and Problem Statement

The model decides to call a tool on behalf of the current user. A tool that reads or
changes protected data needs the same access checks as a controller. The Agent component
does not know about Symfony Security, so the bundle has to add them.

## Decision

The bundle provides the `#[IsGrantedTool]` attribute, which works like Symfony's
`#[IsGranted]`. It can be put on a tool class or method and takes a role or voter
attribute, an optional subject, a message and an error code.

`IsGrantedToolAttributeListener` listens to the Agent event `ToolCallArgumentsResolved`.
This event fires after the model's arguments are known and before the tool runs. The
listener reads the attributes, takes the subject from the arguments (by name, expression or
closure) and asks the authorization checker. If access is denied, it throws an
`AccessDeniedException` (code 403 by default).

If `symfony/security-core` is not installed, the listener is removed, and using the
attribute fails the container build with a clear message.

## Consequences

- **Good:** tools reuse the application's roles and voters, declared right on the tool.
- **Good:** the check can depend on the arguments of the call, for example an order id.
- **Trade-off:** the toolbox turns the denial into a `ToolExecutionException`. With
  `fault_tolerant_toolbox` the model sees it as the tool result, so the message must be safe
  to show to the model.

## Alternatives Considered

- **Checking permissions inside each tool**: not chosen, because it repeats code and hides
  the rule from the class signature.

## References

- `src/Security/Attribute/IsGrantedTool.php`: the attribute.
- `src/Security/EventListener/IsGrantedToolAttributeListener.php`: the check.
- `config/services.php`: the listener registration.
- `src/AiBundle.php`: removing the listener without `symfony/security-core`.
