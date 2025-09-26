# Multi-Agent Configuration

The AI Bundle provides a configuration system for creating multi-agent orchestrators that route requests to specialized agents based on defined handoff rules.

## Configuration

Configure multi-agent systems in your `config/packages/ai.yaml` file:

```yaml
ai:
    multi_agent:
        # Define named multi-agent systems
        support:
            # The main orchestrator agent that analyzes requests
            orchestrator: 'ai.agent.orchestrator'
            
            # Handoff rules defining when to route to specific agents
            # Minimum 2 handoffs required (otherwise use the agent directly)
            handoffs:
                # Route to technical agent for specific keywords
                - to: 'ai.agent.technical'
                  when: ['bug', 'problem', 'technical', 'error', 'code', 'debug']
                
                # Fallback to general agent when no specific conditions match
                - to: 'ai.agent.general'
                  when: []
            
            # Optional: Custom logger service
            logger: 'monolog.logger.ai'
```

## Service Registration

Each multi-agent configuration automatically registers a service with the ID pattern `ai.multi_agent.{name}`.

For the example above, the service `ai.multi_agent.support` is registered and can be injected:

```php
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Target;

class SupportController
{
    public function __construct(
        #[Target('supportMultiAgent')]
        private AgentInterface $supportAgent,
    ) {
    }
    
    #[Route('/ask-support')]
    public function askSupport(string $question): Response
    {
        $messages = new MessageBag(Message::ofUser($question));
        $response = $this->supportAgent->call($messages);
        
        return new Response($response->getContent());
    }
}
```

## Handoff Rules

Handoff rules determine when to delegate to specific agents:

### `to` (required)
The service ID of the target agent to delegate to.

### `when` (optional)
An array of keywords or phrases that trigger this handoff. When the orchestrator identifies these keywords in the user's request, it delegates to the specified agent.

If `when` is empty or not specified, the handoff acts as a fallback for requests that don't match other rules.

## How It Works

1. The orchestrator agent receives the initial request
2. It analyzes the request content and matches it against handoff rules
3. If keywords match a handoff's `when` conditions, the request is delegated to that agent
4. If no specific conditions match, a fallback handoff (with empty `when`) is used
5. The delegated agent processes the request and returns the response

## Requirements

- At least 2 handoff rules must be defined (for a single handoff, use the agent directly)
- The orchestrator and all referenced agents must be registered as services
- All agent services must implement `Symfony\AI\Agent\AgentInterface`

## Example: Customer Service Bot

```yaml
ai:
    multi_agent:
        customer_service:
            orchestrator: 'ai.agent.analyzer'
            handoffs:
                # Technical support
                - to: 'ai.agent.tech_support'
                  when: ['error', 'bug', 'crash', 'not working', 'broken']
                
                # Billing inquiries
                - to: 'ai.agent.billing'
                  when: ['payment', 'invoice', 'billing', 'subscription', 'price']
                
                # Product information
                - to: 'ai.agent.product_info'
                  when: ['features', 'how to', 'tutorial', 'guide', 'documentation']
                
                # General inquiries (fallback)
                - to: 'ai.agent.general_support'
                  when: []
```