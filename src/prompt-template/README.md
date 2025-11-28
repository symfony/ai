# Symfony AI Prompt Template Component

**This Component is [experimental](https://github.com/symfony/ai?tab=readme-ov-file#component-status). Expect breaking changes in minor and patch versions.**

PHP library for prompt template management with extensible rendering strategies.

## Installation

```bash
composer require symfony/ai-prompt-template
```

For advanced expression-based rendering:

```bash
composer require symfony/expression-language
```

## Basic Usage

### Simple Variable Replacement (Default)

```php
use Symfony\AI\PromptTemplate\PromptTemplate;

$template = new PromptTemplate('Hello {name}!');
echo $template->format(['name' => 'World']); // "Hello World!"

// Using static factory
$template = PromptTemplate::fromString(<<<'PROMPT'
You are a helpful assistant.

User: {user}
Query: {query}
PROMPT);

echo $template->format([
    'user' => 'Alice',
    'query' => 'What is AI?',
]);
```

The default `StringRenderer` performs simple `{variable}` replacement with zero external dependencies.

### Advanced Expression-Based Rendering

Install `symfony/expression-language` to use the `ExpressionRenderer`:

```php
use Symfony\AI\PromptTemplate\PromptTemplate;
use Symfony\AI\PromptTemplate\Renderer\ExpressionRenderer;

$renderer = new ExpressionRenderer();
$template = new PromptTemplate('Total: {price * quantity}', $renderer);
echo $template->format(['price' => 10, 'quantity' => 5]); // "Total: 50"

// Conditional expressions
$template = PromptTemplate::fromStringWithRenderer(
    'Status: {age >= 18 ? "adult" : "minor"}',
    new ExpressionRenderer()
);
echo $template->format(['age' => 25]); // "Status: adult"

// Object property access
$template = new PromptTemplate('Name: {user.name}', $renderer);
echo $template->format(['user' => $userObject]); // "Name: Alice"
```

### Custom Renderers

Implement `RendererInterface` to create custom rendering strategies:

```php
use Symfony\AI\PromptTemplate\Renderer\RendererInterface;

class MustacheRenderer implements RendererInterface
{
    public function render(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }
        return $template;
    }
}

$template = new PromptTemplate('Hello {{name}}!', new MustacheRenderer());
echo $template->format(['name' => 'World']); // "Hello World!"
```

## Available Renderers

### StringRenderer (Default)

- Simple `{variable}` placeholder replacement
- Validates variable names and values
- Zero external dependencies
- Supports strings, numbers, and `\Stringable` objects

### ExpressionRenderer (Optional)

Requires `symfony/expression-language`

- Variable access: `{user.name}`
- Math operations: `{price * quantity}`
- Conditionals: `{age > 18 ? "adult" : "minor"}`
- String concatenation: `{firstName ~ " " ~ lastName}`
- Array access: `{items[0]}`
- Custom functions via `ExpressionLanguage`

## API Reference

### PromptTemplate

```php
// Constructor
new PromptTemplate(string $template, ?RendererInterface $renderer = null)

// Methods
$template->format(array $values = []): string
$template->getTemplate(): string
(string) $template // Returns template string

// Static factories
PromptTemplate::fromString(string $template): self
PromptTemplate::fromStringWithRenderer(string $template, RendererInterface $renderer): self
```

### RendererInterface

```php
interface RendererInterface
{
    public function render(string $template, array $values): string;
}
```

## Resources

- [Main Repository](https://github.com/symfony/ai)
- [Report Issues](https://github.com/symfony/ai/issues)
- [Submit Pull Requests](https://github.com/symfony/ai/pulls)
