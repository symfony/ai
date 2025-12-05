# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this component.

## Component Overview

This is the Prompt Template component of the Symfony AI monorepo - an extensible prompt templating system with pluggable rendering strategies. It provides simple variable substitution by default and optional Symfony Expression Language integration for advanced use cases.

## Architecture

### Core Classes

- **PromptTemplate**: Main implementation with renderer injection
- **PromptTemplateInterface**: Core contract for template implementations
- **RendererInterface**: Strategy interface for rendering implementations
- **StringRenderer**: Default renderer using simple {variable} replacement (zero dependencies)
- **ExpressionRenderer**: Optional renderer using Symfony Expression Language for advanced expressions

### Key Features

- **Zero Core Dependencies**: Only requires PHP 8.2+
- **Strategy Pattern**: Pluggable renderer system for extensibility
- **Simple by Default**: Basic {variable} replacement out of the box
- **Advanced Optional**: Expression Language for power users
- **Immutable**: readonly classes ensure thread-safety

### Key Directories

- `src/`: Source code with main classes
- `src/Renderer/`: Renderer implementations
- `src/Exception/`: Component-specific exceptions
- `tests/`: Comprehensive test suite mirroring src structure
- `tests/Renderer/`: Tests for each renderer

## Development Commands

### Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/PromptTemplateTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage
```

### Code Quality

```bash
# Run PHPStan static analysis
vendor/bin/phpstan analyse

# Fix code style (run from project root)
cd ../../.. && vendor/bin/php-cs-fixer fix src/prompt-template/
```

### Installing Dependencies

```bash
# Install dependencies
composer install

# Install with expression language
composer require symfony/expression-language
```

## Testing Architecture

- Uses PHPUnit 11.5+ with strict configuration
- Test fixtures follow monorepo patterns
- Each renderer has dedicated test class
- Integration tests verify renderer injection
- Component-specific exception testing
- Prefer `self::assert*` over `$this->assert*`

## Usage Patterns

### Default Renderer

```php
$template = new PromptTemplate('Hello {name}!');
echo $template->format(['name' => 'World']);
```

### Expression Renderer

```php
$renderer = new ExpressionRenderer();
$template = new PromptTemplate('Total: {price * quantity}', $renderer);
echo $template->format(['price' => 10, 'quantity' => 5]);
```

### Custom Renderer

```php
class MyRenderer implements RendererInterface {
    public function render(string $template, array $values): string {
        // Custom implementation
    }
}

$template = new PromptTemplate($template, new MyRenderer());
```

## Development Notes

- All new classes should have `@author` tags
- Use component-specific exceptions from `src/Exception/`
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- Component is marked as experimental (BC breaks possible)
- StringRenderer must remain dependency-free
- ExpressionRenderer requires symfony/expression-language
- All classes use readonly for immutability
