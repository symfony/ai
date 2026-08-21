# Symfony AI - Mate Component

The Mate component provides a command-line assistant (`vendor/bin/mate`) that exposes
project-aware development tools to coding agents (Claude Code, Codex, Cursor, …) and
developers. Agents run the `mate` commands directly, so tool schemas are read on demand
via `--help`/`tools:inspect` instead of being loaded up front. This is a development tool,
not intended for production use.

Install it in your project with:

```bash
composer require --dev symfony/ai-mate
vendor/bin/mate init
composer dump-autoload
```

Point your coding agent at the CLI (see the generated `mate/AGENT_INSTRUCTIONS.md`):

```bash
vendor/bin/mate tools:list                          # list available tools
vendor/bin/mate tools:inspect symfony-profiler-list # show a tool's parameters/schema
vendor/bin/mate tools:call symfony-profiler-list --limit=1
vendor/bin/mate resources:read symfony-profiler://profile/<token>
```

Add `--format=json` to any command for machine-readable output.

The package ships with the optional `symfony/ai-mate-composer-plugin`, which automatically
refreshes Mate extension discovery after `composer install` and `composer update` once the
project has been initialized.

## Installation

```bash
composer require --dev symfony/ai-mate
```

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/mate.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
