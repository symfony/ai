# System Prompt Translation Examples

This document provides practical examples and patterns for implementing system prompt translation in your Symfony AI applications.

## Advanced Configuration Examples

### Environment-Specific Translation Settings

```yaml
# config/packages/dev/ai.yaml
ai:
    agent:
        system_prompt:
            enable_translation: false  # Disable in development for faster debugging

# config/packages/prod/ai.yaml
ai:
    agent:
        system_prompt:
            enable_translation: true
            translation_domain: 'ai_system_prompts'
```

### Multiple Translation Domains

```php
use Symfony\Component\Ai\Agent\InputProcessor\SystemPromptInputProcessor;

// Service definition for different domains
$customerServiceProcessor = new SystemPromptInputProcessor(
    translator: $translator,
    enableTranslation: true,
    translationDomain: 'customer_service'
);

$technicalSupportProcessor = new SystemPromptInputProcessor(
    translator: $translator,
    enableTranslation: true,
    translationDomain: 'technical_support'
);
```

## Translation File Organization

### Domain-Specific Translation Files

```yaml
# translations/customer_service.en.yaml
greeting: "Hello! I'm your customer service assistant."
escalation: "I'll escalate this to a human representative."
closing: "Thank you for contacting us. Is there anything else I can help you with?"

# translations/technical_support.en.yaml
greeting: "Hi! I'm here to help you with technical issues."
diagnostic: "Let me run some diagnostics to identify the problem."
resolution: "Here's the solution to your technical issue."

# translations/sales.en.yaml
greeting: "Welcome! I'm here to help you find the perfect product."
recommendation: "Based on your needs, I recommend the following products."
```

### Multilingual Examples

```yaml
# translations/customer_service.fr.yaml
greeting: "Bonjour ! Je suis votre assistant du service client."
escalation: "Je vais transmettre cela à un représentant humain."
closing: "Merci de nous avoir contactés. Puis-je vous aider avec autre chose ?"

# translations/customer_service.es.yaml
greeting: "¡Hola! Soy tu asistente de atención al cliente."
escalation: "Escalaré esto a un representante humano."
closing: "Gracias por contactarnos. ¿Hay algo más en lo que pueda ayudarte?"
```

## Integration Patterns

### Service-Based Agent Factory

```php
use Symfony\Component\Ai\Agent\Agent;
use Symfony\Component\Ai\Agent\Prompt\SystemPrompt;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocalizedAgentFactory
{
    public function __construct(
        private PlatformInterface $platform,
        private TranslatorInterface $translator,
        private bool $enableTranslation = true,
        private string $defaultDomain = 'ai_agents'
    ) {}

    public function createCustomerServiceAgent(string $locale = null): Agent
    {
        $this->setLocale($locale);
        
        return new Agent(
            platform: $this->platform,
            systemPrompt: new SystemPrompt('customer_service.main_prompt')
        );
    }

    public function createTechnicalSupportAgent(string $locale = null): Agent
    {
        $this->setLocale($locale);
        
        return new Agent(
            platform: $this->platform,
            systemPrompt: new SystemPrompt('technical_support.main_prompt')
        );
    }

    private function setLocale(?string $locale): void
    {
        if ($locale && method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }
}
```

### Controller Integration

```php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AiChatController extends AbstractController
{
    public function __construct(
        private LocalizedAgentFactory $agentFactory
    ) {}

    #[Route('/chat/{type}', methods: ['POST'])]
    public function chat(Request $request, string $type): JsonResponse
    {
        $locale = $request->getLocale();
        
        $agent = match($type) {
            'customer-service' => $this->agentFactory->createCustomerServiceAgent($locale),
            'technical-support' => $this->agentFactory->createTechnicalSupportAgent($locale),
            default => throw new BadRequestException('Unknown agent type')
        };

        // Use the localized agent...
        $response = $agent->chat($request->getContent());
        
        return $this->json(['response' => $response]);
    }
}
```

### Event-Driven Locale Switching

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleAwareAgentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocalizedAgentFactory $agentFactory
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest'
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->headers->get('Accept-Language');
        
        if ($locale) {
            // Update agent factory with current locale
            $this->agentFactory->setDefaultLocale($locale);
        }
    }
}
```

## Testing Translation Features

### Unit Tests

```php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Ai\Agent\InputProcessor\SystemPromptInputProcessor;

class SystemPromptTranslationTest extends TestCase
{
    public function testSystemPromptTranslation(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'greeting' => 'Hello, I am your AI assistant'
        ], 'en');
        $translator->addResource('array', [
            'greeting' => 'Hola, soy tu asistente de IA'
        ], 'es');

        $processor = new SystemPromptInputProcessor(
            translator: $translator,
            enableTranslation: true
        );

        // Test English translation
        $translator->setLocale('en');
        $result = $processor->processInput('greeting');
        $this->assertEquals('Hello, I am your AI assistant', $result);

        // Test Spanish translation
        $translator->setLocale('es');
        $result = $processor->processInput('greeting');
        $this->assertEquals('Hola, soy tu asistente de IA', $result);
    }
}
```

### Integration Tests

```php
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TranslatedAgentIntegrationTest extends WebTestCase
{
    public function testAgentRespondsInCorrectLanguage(): void
    {
        $client = static::createClient();
        
        // Test English response
        $client->request('POST', '/chat/customer-service', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en'
        ], '{"message": "Hello"}');
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContains('Hello', $response['response']);

        // Test Spanish response
        $client->request('POST', '/chat/customer-service', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'es'
        ], '{"message": "Hola"}');
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContains('Hola', $response['response']);
    }
}
```

## Best Practices

1. **Organize translation keys hierarchically** - Use dot notation for better organization
2. **Use consistent naming conventions** - Follow a pattern like `{agent_type}.{prompt_type}`
3. **Test with multiple locales** - Ensure all supported languages work correctly
4. **Fallback handling** - Always provide default English translations
5. **Performance considerations** - Consider caching translated prompts for high-traffic applications
6. **Content management** - Consider using translation management tools for non-technical team members

## Performance Optimization

### Caching Translated Prompts

```php
use Symfony\Contracts\Cache\CacheInterface;

class CachedSystemPromptInputProcessor extends SystemPromptInputProcessor
{
    public function __construct(
        private CacheInterface $cache,
        TranslatorInterface $translator = null,
        bool $enableTranslation = false,
        string $translationDomain = null
    ) {
        parent::__construct($translator, $enableTranslation, $translationDomain);
    }

    public function processInput(string $prompt): string
    {
        if (!$this->enableTranslation || !$this->translator) {
            return $prompt;
        }

        $locale = $this->translator->getLocale();
        $cacheKey = "system_prompt_{$locale}_{$prompt}_{$this->translationDomain}";

        return $this->cache->get($cacheKey, function() use ($prompt) {
            return parent::processInput($prompt);
        });
    }
}
```

This comprehensive documentation provides everything developers need to implement and use system prompt translation in their Symfony AI applications.