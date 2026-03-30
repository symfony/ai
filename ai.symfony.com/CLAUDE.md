# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

There is no test suite for this application.

## Architecture

- **Single route**: `DefaultController::homepage()` serves `/` rendering `homepage.html.twig`
- **Asset Mapper** (not Webpack): Frontend assets managed via Symfony's importmap system (`importmap.php`). No npm/node required.
- **Stimulus controllers** in `assets/controllers/`: `typed_controller.js` (typing animation for hero code example), `csrf_protection_controller.js`
- **Bootstrap 5** for layout/styling, with custom CSS variables in `assets/styles/app.css` supporting light/dark theme toggle
- **Templates**: `templates/base.html.twig` (layout), `templates/homepage.html.twig` (page content), `templates/_header.html.twig` (navigation partial)

## Key Files

- `importmap.php` — Declares all JS/CSS dependencies and their versions (replaces package.json)
- `assets/app.js` — Entry point; handles theme switching and initializes Stimulus
- `assets/styles/app.css` — All custom styles including CSS variables for theming
- `config/reference.php` — Auto-generated config reference (do not edit manually)

## PHP Version

Requires PHP 8.4+.
