# Symfony AI - Mate Component

The Mate component provides an MCP (Model Context Protocol) server that enables AI
assistants to interact with PHP applications (including Symfony) through standardized
tools. This is a development tool, not intended for production use.

Install it in your project with:

```bash
composer require --dev symfony/ai-mate
vendor/bin/mate init
composer dump-autoload
```

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
