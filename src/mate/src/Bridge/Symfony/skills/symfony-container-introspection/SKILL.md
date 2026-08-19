---
name: symfony-container-introspection
description: >-
  Inspect a Symfony dependency-injection container through Mate's service tools.
  Use when the user hits a ServiceNotFoundException or autowiring failure, asks
  whether a service is registered or what class an id resolves to, wants every
  service carrying a given DI tag (event listeners, Twig extensions, …), or needs
  to know how a service is constructed (factory, tags, method calls).
---

# Inspecting the Symfony service container

This skill turns Mate's container capabilities into a search → detail workflow. It
reads the compiled `*DebugContainer.xml` and auto-detects the environment
(dev/test/prod), so it reflects the *real* compiled container, not source
annotations.

## Capabilities you will use

| Capability | Type | Purpose |
|---|---|---|
| `symfony-services` | tool | Search/filter the container; returns a map of service id → class. |
| `symfony-service-detail` | tool | Full definition of one service by exact id: class, tags, calls, factory. |

## Workflow

Follow **search → detail**: cast a filter to find the right id, then open that one
id for the full definition. Do not ask for detail before you have an exact id.

### 1. Search

Call `symfony-services` with the narrowest filter that fits the question:

- **By id or class** — `query` does a case-insensitive partial match against both
  the service id and its class. `query=mailer`, `query=App\\Service\\Invoice`.
- **By tag** — `tag` returns every service carrying a DI tag, e.g.
  `tag=kernel.event_listener`, `tag=twig.extension`,
  `tag=monolog.logger`.

The result is a triage map (`id => class`). An **empty result is itself the
answer** to "is this registered?" — it is not.

### 2. Detail

Once you have an **exact** id, call `symfony-service-detail` with it to get:

- `class` — the concrete class behind the id;
- `tags` — each tag with its attributes (priority, event, alias, …);
- `calls` — setter/method calls applied after instantiation;
- `factory` — present only when the service is built via a factory
  (`Class::method`) rather than a plain constructor.

## Symptom → move

| Symptom / question | Move |
|---|---|
| `ServiceNotFoundException: "app.foo"` | `symfony-services query=foo` — find the real id or confirm it is missing. |
| "Autowiring can't find an argument of type `X`" | `symfony-services query=X` — is the class registered, and under which id/alias? |
| "What is `mailer` actually?" | `symfony-service-detail id=mailer`. |
| "Which listeners run on `kernel.request`?" | `symfony-services tag=kernel.event_listener`, then detail the candidates and read their tag `event`/`priority`. |
| "Why isn't my decorator/factory used?" | `symfony-service-detail` on the id — check `factory` and `calls`. |
| Error happens *during a request* | This is a profiler case, not a container one — use the
  [symfony-profiler-debugging](../symfony-profiler-debugging/SKILL.md) skill. |

## Rules

- **Filter before you fetch.** `symfony-services` with no filter can return a very
  large map; always pass `query` or `tag` unless the user truly wants everything.
- **Match the exact id for detail.** `symfony-service-detail` needs the precise id
  from the search step — partial ids fail.
- **Report the resolution, not the dump.** Answer with the id/class/tag that
  resolves the question (e.g. "`app.invoice_mailer` → `App\\Mailer\\InvoiceMailer`,
  tagged `kernel.event_listener` on `order.placed`"), not the whole definition.
