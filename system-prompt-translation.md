# System Prompt Translation

The Symfony AI Agent component supports optional translation of system prompts, allowing you to provide localized system prompt text in different languages.

## Overview

System prompt translation enables AI agents to use system prompts in any locale by leveraging Symfony's translation system. This feature is particularly useful for applications that need to provide AI interactions in multiple languages.

## Configuration

### Bundle Configuration

To enable system prompt translation in your Symfony application, configure the AI bundle:

```yaml
# config/packages/ai.yaml
ai:
    agent:
        system_prompt:
            enable_translation: true
            translation_domain: 'ai_prompts'  # optional
```

### Configuration Options

- `enable_translation` (boolean): Enable or disable system prompt translation. Default: `false`
- `translation_domain` (string, optional): Specify a custom translation domain for system prompts

## Usage

### Basic Translation

When translation is enabled, system prompts will automatically be translated using Symfony's translator:

```php
use Symfony\Component\Ai\Agent\Agent;
use Symfony\Component\Ai\Agent\Prompt\SystemPrompt;

// Create a system prompt that will be translated
$systemPrompt = new SystemPrompt('greeting.assistant');

// The agent will automatically translate this prompt
$agent = new Agent(
    platform: $platform,
    systemPrompt: $systemPrompt
);
```

### Translation Files

Create translation files for your system prompts:

```yaml
# translations/messages.en.yaml
greeting:
    assistant: "You are a helpful AI assistant."

# translations/messages.fr.yaml
greeting:
    assistant: "Vous êtes un assistant IA utile."

# translations/messages.es.yaml
greeting:
    assistant: "Eres un asistente de IA útil."
```

### Custom Translation Domain

When using a custom translation domain:

```yaml
# config/packages/ai.yaml
ai:
    agent:
        system_prompt:
            enable_translation: true
            translation_domain: 'ai_prompts'
```

```yaml
# translations/ai_prompts.en.yaml
customer_service: "You are a customer service representative..."
technical_support: "You are a technical support specialist..."

# translations/ai_prompts.fr.yaml
customer_service: "Vous êtes un représentant du service client..."
technical_support: "Vous êtes un spécialiste du support technique..."
```

## Requirements

- Symfony's Translation component must be available
- The TranslatorInterface service must be registered in your application
- Translation files must be properly configured

## Backward Compatibility

The translation feature is completely optional and backward compatible:

- If translation is disabled (default), system prompts work exactly as before
- If no translator is available, prompts are used as-is without translation
- Existing code requires no changes to continue working

## Example: Multilingual Customer Service Agent

```php
use Symfony\Component\Ai\Agent\Agent;
use Symfony\Component\Ai\Agent\Prompt\SystemPrompt;

class CustomerServiceAgentFactory
{
    public function __construct(
        private PlatformInterface $platform,
        private string $locale = 'en'
    ) {}

    public function createAgent(): Agent
    {
        // This prompt will be translated based on current locale
        $systemPrompt = new SystemPrompt('customer_service.greeting');
        
        return new Agent(
            platform: $this->platform,
            systemPrompt: $systemPrompt
        );
    }
}
```

```yaml
# translations/messages.en.yaml
customer_service:
    greeting: "You are a friendly customer service representative. Always be helpful and professional."

# translations/messages.de.yaml
customer_service:
    greeting: "Sie sind ein freundlicher Kundendienstmitarbeiter. Seien Sie immer hilfsbereit und professionell."
```

## Notes

- Translation keys in system prompts should follow standard Symfony translation key conventions
- Parameters can be passed to translated prompts using standard Symfony translation parameter syntax
- The current request locale is used for translation, allowing automatic language switching based on user preferences