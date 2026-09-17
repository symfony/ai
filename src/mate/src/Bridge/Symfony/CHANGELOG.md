CHANGELOG
=========

0.14
----

 * Add `symfony-dotenv-check` tool and `symfony-dotenv-diagnostics` skill: reports which `.env*` file declares each variable and whether it resolves at runtime, without ever returning a raw value (only a masked length + first/last-character preview), as a safe replacement for `bin/console debug:dotenv`, which prints fully resolved secrets in clear text. Requires `symfony/dotenv`

0.13
----

 * Add the `symfony-profiler-debugging`, `symfony-request-triage` and `symfony-service-inspection` skills, which used to ship from `symfony/ai-mate` itself and were therefore installed into projects that do not have this extension and cannot run the tools they describe
 * Mark the output of `symfony-profiler-list`, `symfony-profiler-get`, `symfony-services`, `symfony-service-detail` and the profiler resources as untrusted data, since request/response payloads, SQL queries and container metadata are frequently controlled by end users or third-party packages
 * Allow `ai_mate_symfony.cache_dir` to be a map of context name to cache directory, so a single Mate server can introspect the containers of multi-kernel (`APP_ID`) applications. `symfony-services` then returns the services grouped per context, `symfony-service-detail` reports the context a service was found in, and both accept an optional `context` filter parameter

0.7
---

 * Merge `symfony-profiler-search` into `symfony-profiler-list` with `from` and `to` date filter parameters
 * Remove `symfony-profiler-latest` tool (use `symfony-profiler-list` with `limit: 1` instead)
 * Add `query` parameter to `symfony-services` for filtering by service ID or class name
 * Add `@param` docblocks to all tool methods for AI-readable parameter descriptions
 * Add automatic detection of compiled container XML for kernels with custom class names
 * Add `DoctrineCollectorFormatter` to expose Doctrine DBAL query data (query count, execution times, SQL, duplicate detection) to AI via the profiler
 * Add optional TOON format encoding for `ServiceTool`, `ProfilerTool`, and `ProfilerResourceTemplate` to reduce token consumption

0.6
---

 * Add `MailerCollectorFormatter` to expose Symfony Mailer data (recipients, body preview, links, attachments, transport) to AI via the profiler
 * Add `TranslationCollectorFormatter` to expose Symfony Translation data (locale, fallback locales, message states) to AI via the profiler

0.3
---

 * Add profiler data access capabilities
 * Add `INSTRUCTIONS.md` with AI agent guidance for container introspection tools

0.1
---

 * Add bridge
