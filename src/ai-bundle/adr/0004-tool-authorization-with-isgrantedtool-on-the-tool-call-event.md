---
status: accepted
date: 2026-10-25
---

# 0004. Tool authorization with #[IsGrantedTool] on the tool call event

## Context and Problem Statement

A tool runs with the permissions of the application, but the decision to call it is made by
a model on behalf of the current user. A tool that reads or changes protected data therefore
needs the same access checks as a controller, and the model's arguments must be available
as the voter subject. The Agent component itself has no knowledge of Symfony Security.

## Decision

The bundle provides `Security\Attribute\IsGrantedTool`, modeled on Symfony's `#[IsGranted]`:
a repeatable class or method attribute with an `attribute` (role, voter attribute or
`Expression`), an optional `subject`, a `message` and an `exceptionCode`.

`Security\EventListener\IsGrantedToolAttributeListener` listens to the Agent component's
`ToolCallArgumentsResolved` event, which is dispatched after the model's arguments are
resolved and before the tool runs. It reads the attributes of the tool class and of the
called method, resolves the subject from an argument name, an `Expression` (with `tool` and
`args` variables) or a closure, and calls `AuthorizationCheckerInterface::isGranted()`. A
denial throws `AccessDeniedException` with code 403 unless another code is configured.

The listener is registered as `ai.security.is_granted_attribute_listener` in
`config/services.php`. When `symfony/security-core` is not installed, `AiBundle` removes it
and makes any use of `#[IsGrantedTool]` fail at container build time with an
`InvalidArgumentException` asking to install the package.

## Consequences

- **Good:** authorization reuses the application's voters and role hierarchy, and is
  declared next to the tool it protects.
- **Good:** the check runs on the resolved arguments, so a voter can decide per subject
  (for example per order id passed by the model).
- **Trade-off:** the check depends on the event dispatcher being passed to the toolbox; a
  toolbox built without it does not enforce the attribute.
- **Trade-off:** the toolbox wraps the `AccessDeniedException` in a `ToolExecutionException`;
  with `fault_tolerant_toolbox` enabled the model receives the failure as the tool result
  instead of the call aborting, so the denial message must be safe to show to the model.

## Alternatives Considered

- **Checking permissions inside each tool**: not chosen because it repeats boilerplate and
  hides the requirement from readers of the class signature.
- **Filtering the tool list per user**: not chosen as the mechanism because it cannot decide
  on the arguments of a single call.

## References

- `src/Security/Attribute/IsGrantedTool.php`: the attribute.
- `src/Security/EventListener/IsGrantedToolAttributeListener.php`: subject resolution and
  the access check.
- `config/services.php`: listener registration on `ToolCallArgumentsResolved`.
- `src/AiBundle.php`: removal of the listener without `symfony/security-core`.
