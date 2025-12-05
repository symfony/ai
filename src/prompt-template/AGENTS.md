# AGENTS.md

AI agent guidance for the Prompt Template component.

## Component Overview

Extensible prompt templating system with pluggable rendering strategies. Provides simple variable substitution by default and optional Symfony Expression Language integration for advanced use cases.

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
- `src/Exception/`: Component-specific exceptions (factory pattern)
- `tests/`: Comprehensive test suite mirroring src structure
- `tests/Renderer/`: Tests for each renderer

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/PromptTemplateTest.php
vendor/bin/phpunit --coverage-html coverage
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/prompt-template/
```

### Dependencies
```bash
composer install
composer require symfony/expression-language  # For ExpressionRenderer
```

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

## Testing Patterns

- PHPUnit 11.5+ with strict configuration
- Test fixtures follow monorepo patterns
- Each renderer has dedicated test class
- Integration tests verify renderer injection
- Component-specific exception testing using factory pattern
- Prefer `self::assert*` over `$this->assert*`

## Development Notes

- Add `@author` tags to new classes
- Use component-specific exceptions from `src/Exception/` with factory pattern
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- Component is experimental (BC breaks possible)
- StringRenderer must remain dependency-free
- ExpressionRenderer requires symfony/expression-language
- All classes use readonly for immutability
