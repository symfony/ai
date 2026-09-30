# AGENTS.md

AI agent guidance for the ai.symfony.com marketing website.

## Overview

This is the **ai.symfony.com** marketing website — a lightweight Symfony 8.0 application serving the landing page for the Symfony AI project. It has a single controller, no database, and no complex backend logic.

## Development Commands

```bash
# Install dependencies
composer install

# Start development server
symfony server:start

# Clear cache
bin/console cache:clear

# List available asset paths
bin/console debug:asset-map

# Add a new JS/CSS dependency via importmap
bin/console importmap:require <package>
```

```bash
# Run the test suite
vendor/bin/phpunit
```

## Architecture

- **Routes**: `DefaultController` serves `/` rendering `homepage.html.twig`, `CookbookController` the cookbook articles and `PlatformBridgeController` the platform bridges page
- **Asset Mapper** (not Webpack): Frontend assets managed via Symfony's importmap system (`importmap.php`). No npm/node required.
- **Stimulus controllers** in `assets/controllers/`: `typed_controller.js` (typing animation for hero code example), `hero_slider_controller.js`, `feature_tabs_controller.js`, `clipboard_controller.js`, `csrf_protection_controller.js`
- **Bootstrap 5** for layout/styling, with custom CSS variables in `assets/styles/app.css` supporting light/dark theme toggle
- **Templates**: `templates/base.html.twig` (layout), `templates/homepage.html.twig` (page content), `templates/_header.html.twig` (navigation partial)

## Platform Bridges Page

`/bridges/platforms` lists every platform bridge of `src/platform/src/Bridge` with faceted filters (Live Component `PlatformBridgeList`, filters kept in the query string). It only reads local files:

- `var/share/platform_bridges.json`: the bridges listed in `splitsh.json` on GitHub and their downloads on Packagist, written every morning by the `app:platform-bridges:update` cron (see `.upsun/config.yaml`). Run the command once locally to get download counts; without the file, the bridges come from the curation only.
- `config/platform_bridges.yaml` classifies each bridge (kind, regions, hosting, capabilities...). Add an entry for every new bridge, only with facts documented by the provider; a test checks that every bridge of `splitsh.json` has one.

## Key Files

- `importmap.php` — Declares all JS/CSS dependencies and their versions (replaces package.json)
- `assets/app.js` — Entry point; handles theme switching and initializes Stimulus
- `assets/styles/app.css` — All custom styles including CSS variables for theming
- `config/reference.php` — Auto-generated config reference (do not edit manually)

## PHP Version

Requires PHP 8.4+.
