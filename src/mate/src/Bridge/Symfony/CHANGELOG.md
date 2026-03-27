CHANGELOG
=========

0.7
---

 * Merge `symfony-profiler-search` into `symfony-profiler-list` with `from` and `to` date filter parameters
 * Remove `symfony-profiler-latest` tool (use `symfony-profiler-list` with `limit: 1` instead)
 * Add `query` parameter to `symfony-services` for filtering by service ID or class name
 * Add `@param` docblocks to all tool methods for AI-readable parameter descriptions
 * Add automatic detection of compiled container XML for kernels with custom class names

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
