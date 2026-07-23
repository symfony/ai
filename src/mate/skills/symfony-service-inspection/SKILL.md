---
name: symfony-service-inspection
description: Diagnose a Symfony dependency-injection or wiring problem, service not found, wrong implementation injected, an autoconfigured tag not applied, or a factory/decorator not doing what you expect. Use for container/config bugs, not runtime request errors (profiler), log trends (log investigation), or a missing PHP extension breaking the tool itself (php environment check).
---

# Service inspection

Reads the compiled DI container through Mate's CLI, from the dumped `*DebugContainer.xml`. Two tools:

- `symfony-services` (opt `query`, `tag`): `query` is a case-insensitive partial match on service id OR class; `tag` is an exact tag name. Returns a map of `id => class`.
- `symfony-service-detail --id=<exact id>`: full detail for one service, `{id, class, tags, calls, factory?}`. The id must be exact.

Every command accepts `--format`: `json` to parse the result, `toon` (when `helgesverre/toon` is installed) for the smallest context footprint. The service map can be large, so filter it rather than dumping it wide.

## Workflow

1. Locate candidates with `symfony-services`, filtered. Never dump the whole container.
   - By class or interface: `mate tools:call symfony-services --query=MailerInterface`
   - By id fragment: `--query=app.handler`
   - By tag, to see who participates in a hook: `mate tools:call symfony-services --tag=kernel.event_listener`
2. Take the exact id from that map and read it: `mate tools:call symfony-service-detail --id=App\\Mailer\\Mailer`.
3. Interpret against the symptom below.

## Reading

- **Wrong implementation injected:** query the interface. Every id mapping to a class is a candidate. The one wired in is usually the alias whose id equals the interface FQCN. Aliases are resolved for you: asking for the alias id returns the target's class, tags, and calls, so `detail` on the interface id shows the concrete class that actually gets injected.
- **Tag not applied (listener/subscriber/extension silent):** `detail` the service and check `tags`. If the expected tag is absent, autoconfiguration did not fire, usually because the class does not implement the expected interface/attribute, or `autoconfigure` is off. Each tag entry carries its attributes (`event`, `priority`, `method`, ...); a listener bound to the wrong `event` or `priority` is a common cause.
- **Constructor/factory surprise:** `factory` (present only when set) is `Class::method`, telling you the object is built by a factory, not `new`. `calls` lists setter-injection method names invoked after construction. A dependency that is null at runtime is often a missing setter call here.

## Failure paths

- `symfony-service-detail` errors "Service ... not found": the id is not exact. Ids are case-sensitive and a leading dot is stripped (`.inner` is stored as `inner`). Re-run `symfony-services` with a fragment to copy the real id. Note a service can exist yet be private; it still appears here.
- `symfony-services` returns an empty map: the container XML could not be found or loaded. The dump only exists after the container is compiled. Warm it (`bin/console cache:warmup`, or just boot the app once) in the environment you are inspecting, then retry. If it is still empty after warming, the parse itself may be failing because the runtime lacks `simplexml`; confirm the environment with php environment check.
- Reads the first of dev/test/prod it finds. If you are chasing an env-specific binding, make sure that environment's container has been compiled, otherwise you are reading dev.
