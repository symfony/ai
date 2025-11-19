# AI Agents - Complete Learning Guide

## 📚 What is an AI Agent?

An **AI Agent** is an intelligent system that can:
1. **Perceive** - Understand user input and context
2. **Reason** - Use an LLM (Large Language Model) to think and plan
3. **Act** - Execute actions using tools to interact with the world
4. **Learn** - Remember conversation history and improve responses

Think of it as giving an AI model "hands" to perform real tasks like searching Wikipedia, checking weather, or querying databases.

---

## 🏗️ Architecture Overview

```
User Input
    ↓
[Agent] ← Contains the orchestration logic
    ↓
[LLM Platform] (e.g., OpenAI, Anthropic)
    ↓
[Tools/Toolbox] ← Functions the agent can call
    ↓
External Systems (APIs, Databases, etc.)
    ↓
Response → User
```

### Key Components:

1. **Agent** - The main orchestrator
2. **Platform** - The LLM provider (OpenAI, Anthropic, etc.)
3. **Model** - Specific LLM (GPT-4, Claude, etc.)
4. **Tools** - Functions the agent can execute
5. **Memory** - Conversation history storage
6. **Processors** - Input/Output middleware

---

## 🎯 Core Concepts

### 1. Agent
The brain of the system that coordinates everything:

```php
use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;

// Create platform connection
$platform = PlatformFactory::create($apiKey);

// Choose model
$model = new Gpt(Gpt::GPT_4O_MINI);

// Create agent
$agent = new Agent($platform, $model);
```

### 2. Messages
How you communicate with the agent:

```php
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant'),
    Message::ofUser('What is the weather today?'),
);

$result = $agent->call($messages);
echo $result->getContent();
```

### 3. Tools
Functions the agent can call to perform actions:

```php
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('get_weather', 'Get current weather for a location')]
class WeatherTool
{
    public function __invoke(string $location): array
    {
        // Call weather API
        return ['temp' => 72, 'condition' => 'sunny'];
    }
}
```

### 4. Toolbox
Container for all tools:

```php
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\AgentProcessor;

$weatherTool = new WeatherTool();
$toolbox = new Toolbox([$weatherTool]);
$processor = new AgentProcessor($toolbox);

// Add to agent
$agent = new Agent(
    $platform,
    $model,
    inputProcessors: [$processor],
    outputProcessors: [$processor]
);
```

---

## 🛠️ How to Create Your First Agent

### Step 1: Basic Agent (No Tools)

```php
<?php

require 'vendor/autoload.php';

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

// Setup
$platform = PlatformFactory::create('your-api-key');
$model = new Gpt(Gpt::GPT_4O_MINI);
$agent = new Agent($platform, $model);

// Use
$messages = new MessageBag(
    Message::forSystem('You are a helpful coding assistant'),
    Message::ofUser('Explain what an API is')
);

$result = $agent->call($messages);
echo $result->getContent();
```

### Step 2: Agent with Custom Tool

```php
<?php

require 'vendor/autoload.php';

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

// 1. Define your tool
#[AsTool('calculate', 'Performs basic math calculations')]
class Calculator
{
    /**
     * @param float $a First number
     * @param float $b Second number
     * @param string $operation Operation to perform (add, subtract, multiply, divide)
     */
    public function __invoke(float $a, float $b, string $operation): float
    {
        return match($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : 0,
            default => 0,
        };
    }
}

// 2. Setup agent with tool
$platform = PlatformFactory::create('your-api-key');
$model = new Gpt(Gpt::GPT_4O_MINI);

$calculator = new Calculator();
$toolbox = new Toolbox([$calculator]);
$processor = new AgentProcessor($toolbox);

$agent = new Agent(
    $platform,
    $model,
    inputProcessors: [$processor],
    outputProcessors: [$processor]
);

// 3. Use the agent
$messages = new MessageBag(
    Message::ofUser('What is 25 multiplied by 4?')
);

$result = $agent->call($messages);
echo $result->getContent(); // The agent will use the calculator tool
```

### Step 3: Agent with Multiple Tools

```php
#[AsTool('current_time', 'Gets the current date and time')]
class Timetool
{
    public function __invoke(): string
    {
        return date('Y-m-d H:i:s');
    }
}

#[AsTool('user_info', 'Gets information about a user', method: 'get')]
class UserService
{
    /**
     * @param int $userId The ID of the user
     */
    public function get(int $userId): array
    {
        // Fetch from database
        return ['id' => $userId, 'name' => 'John Doe', 'email' => 'john@example.com'];
    }
}

// Create toolbox with multiple tools
$toolbox = new Toolbox([
    new Calculator(),
    new TimeTool(),
    new UserService(),
]);

$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor]);
```

### Step 4: Agent with Memory (Chat)

```php
use Symfony\AI\Agent\Chat;
use Symfony\AI\Agent\Chat\SessionMessageStore;

// Create agent
$agent = new Agent($platform, $model);

// Add memory
$store = new SessionMessageStore($session);
$chat = new Chat($agent, $store);

// Initialize with system prompt
$chat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant')
));

// User messages are remembered
$response1 = $chat->submit(Message::ofUser('My name is Alice'));
$response2 = $chat->submit(Message::ofUser('What is my name?'));
// Agent remembers: "Your name is Alice"
```

---

## 🔍 Real-World Examples from the Demo

### Example 1: Wikipedia Search Agent

```php
// From demo/src/Wikipedia/Chat.php
#[Autowire(service: 'ai.agent.wikipedia')]
private readonly AgentInterface $agent;

public function submitMessage(string $message): void
{
    $messages = $this->loadMessages(); // Load history
    $messages->add(Message::ofUser($message));

    $result = $this->agent->call($messages); // Agent uses Wikipedia tool

    $messages->add(Message::ofAssistant($result->getContent()));
    $this->saveMessages($messages); // Save history
}
```

Configuration (config/packages/ai.yaml):
```yaml
ai:
    agent:
        wikipedia:
            model:
                class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI
            system_prompt: 'Answer questions based on Wikipedia'
            tools:
                - 'Symfony\AI\Agent\Toolbox\Tool\Wikipedia'
```

### Example 2: Blog Search Agent (RAG)

Uses Retrieval-Augmented Generation with vector store:

```php
// From demo/src/Blog/Chat.php
ai:
    agent:
        blog:
            tools:
                - 'Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch'
            system_prompt: |
                Use the 'similarity_search' tool to find relevant content.
                Only use information from the tool results.
```

The `SimilaritySearch` tool:
1. Converts user query to embeddings
2. Searches vector database (ChromaDB)
3. Returns relevant documents
4. Agent uses them to answer

---

## 🎓 Learning Path

### Beginner Level
1. ✅ Understand what agents are (this guide)
2. ✅ Create a basic agent without tools
3. ✅ Add a simple custom tool
4. ✅ Test with different prompts

### Intermediate Level
1. Create multiple tools for different tasks
2. Implement conversation memory
3. Use built-in tools (Wikipedia, Weather, etc.)
4. Handle tool errors gracefully
5. Add input/output processors

### Advanced Level
1. Build RAG (Retrieval-Augmented Generation) agents
2. Create agent-in-agent systems (meta-agents)
3. Implement custom processors
4. Add event listeners for tool calls
5. Build streaming responses
6. Create multi-step workflows

---

## 📖 Built-in Tools You Can Use

The Symfony AI bundle provides several ready-to-use tools:

### 1. Wikipedia
```php
use Symfony\AI\Agent\Toolbox\Tool\Wikipedia;

$wikipedia = new Wikipedia($httpClient);
// Provides: wikipedia_search and wikipedia_article
```

### 2. Clock
```php
use Symfony\Component\Clock\Clock;

// Add to toolbox
$toolbox = new Toolbox([new Clock()]);
// Provides current time/date
```

### 3. OpenMeteo (Weather)
```php
use Symfony\AI\Agent\Toolbox\Tool\OpenMeteo;

$weather = new OpenMeteo($httpClient);
// Provides: weather_current and weather_forecast
```

### 4. SimilaritySearch (Vector Search)
```php
use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;

$search = new SimilaritySearch($store, $model);
// Searches vector database for relevant documents
```

### 5. Web Crawlers
```php
use Symfony\AI\Agent\Toolbox\Tool\Firecrawl;
use Symfony\AI\Agent\Toolbox\Tool\Brave;

// Various web scraping and search tools
```

---

## 🧪 Hands-On Practice Ideas

### Project 1: Personal Assistant
Create an agent that can:
- Tell time
- Perform calculations
- Search Wikipedia
- Remember user preferences

### Project 2: Code Helper
Create an agent with tools to:
- Search documentation
- Generate code snippets
- Explain concepts
- Debug code

### Project 3: Research Assistant
Create an agent that:
- Searches multiple sources
- Summarizes articles
- Saves findings to a database
- Generates reports

### Project 4: Customer Support Bot
- Answer FAQs from a knowledge base
- Create support tickets
- Check order status
- Escalate to humans when needed

---

## 🔧 Advanced Patterns

### Pattern 1: Agent-in-Agent
Use one agent as a tool for another:

```yaml
ai:
    agent:
        main_agent:
            tools:
                - agent: 'specialized_agent'
                  name: 'specialist'
                  description: 'Expert in specific domain'
```

### Pattern 2: Event-Driven Tools
React to tool execution:

```php
$eventDispatcher->addListener(ToolCallsExecuted::class,
    function (ToolCallsExecuted $event): void {
        // Modify result, log, cache, etc.
    }
);
```

### Pattern 3: Fault-Tolerant Toolbox
Handle tool failures gracefully:

```php
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;

$toolbox = new FaultTolerantToolbox($tools, logger: $logger);
// Will log errors but continue execution
```

---

## 📚 Further Reading

### Documentation
- Symfony AI Bundle: `/src/agent/doc/index.rst`
- Platform Documentation: `/src/platform/doc/`
- Examples: `/examples/` directory

### Key Files to Study
1. `/src/agent/src/Agent.php` - Main agent implementation
2. `/src/agent/src/Toolbox/Toolbox.php` - Tool management
3. `/demo/src/Blog/Chat.php` - Real-world chat example
4. `/examples/toolbox/` - Tool examples

### Concepts to Explore
- **Prompt Engineering**: Crafting effective system prompts
- **RAG (Retrieval-Augmented Generation)**: Using vector stores
- **Function Calling**: How LLMs decide to use tools
- **Streaming**: Real-time response generation
- **Embeddings**: Converting text to vectors for search

---

## 💡 Best Practices

1. **Clear Tool Descriptions**: LLMs use descriptions to decide when to call tools
2. **Type Hints**: Always use type hints for tool parameters
3. **Error Handling**: Tools should handle errors gracefully
4. **Idempotency**: Tools should be safe to call multiple times
5. **Logging**: Log tool calls for debugging and monitoring
6. **Testing**: Write tests for your tools independently
7. **System Prompts**: Be specific about when to use which tools
8. **Context Management**: Don't overload the context window

---

## 🚀 Next Steps

1. **Run the demo app** (already done! ✅)
2. **Experiment** with different prompts in the demo
3. **Read the code** in `/demo/src/` to see patterns
4. **Create a simple tool** following the examples
5. **Build a mini-project** using the concepts learned
6. **Study advanced examples** in `/examples/`
7. **Explore other platforms** (Anthropic, Gemini, etc.)

---

## 🎯 Quick Reference

### Create Agent
```php
$agent = new Agent($platform, $model, $inputProcessors, $outputProcessors);
```

### Create Tool
```php
#[AsTool('name', 'description')]
class MyTool {
    public function __invoke(/* params */): mixed { }
}
```

### Use Agent
```php
$messages = new MessageBag(Message::ofUser('query'));
$result = $agent->call($messages);
```

### Add Tools
```php
$toolbox = new Toolbox([$tool1, $tool2]);
$processor = new AgentProcessor($toolbox);
```

---

## 🤔 Common Questions

**Q: When should I use an agent vs direct LLM call?**
A: Use agents when you need the LLM to interact with external systems or perform actions.

**Q: How do I choose which model to use?**
A: Start with GPT-4O-MINI for cost-effectiveness. Use GPT-4 for complex reasoning.

**Q: Can I use multiple platforms?**
A: Yes! You can create different agents for different platforms.

**Q: How do I handle tool errors?**
A: Use FaultTolerantToolbox or implement try-catch in your tools.

**Q: What's the difference between Agent and Chat?**
A: Agent handles single interactions. Chat manages conversation history.

---

Happy learning! 🎉

Start with the simple examples and gradually build up to more complex agents. The demo app you just ran is a perfect reference implementation!
