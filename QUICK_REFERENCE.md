# AI Agent Quick Reference

## 🎯 What You've Learned

### Core Concept
**AI Agents = LLM + Tools + Memory**
- **LLM**: The brain (GPT, Claude, etc.)
- **Tools**: Functions the agent can execute
- **Memory**: Conversation history

---

## 📝 Essential Code Patterns

### 1. Create a Simple Agent
```php
$platform = PlatformFactory::create($apiKey);
$model = new Gpt(Gpt::GPT_4O_MINI);
$agent = new Agent($platform, $model);

$messages = new MessageBag(Message::ofUser('Hello'));
$result = $agent->call($messages);
echo $result->getContent();
```

### 2. Create a Custom Tool
```php
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('tool_name', 'What this tool does')]
class MyTool
{
    /**
     * @param string $param Description of param
     */
    public function __invoke(string $param): mixed
    {
        // Your logic here
        return $result;
    }
}
```

### 3. Add Tools to Agent
```php
$tool = new MyTool();
$toolbox = new Toolbox([$tool]);
$processor = new AgentProcessor($toolbox);

$agent = new Agent(
    $platform,
    $model,
    inputProcessors: [$processor],
    outputProcessors: [$processor]
);
```

### 4. Multiple Tools in One Class
```php
#[AsTool('tool_one', 'First tool', method: 'methodOne')]
#[AsTool('tool_two', 'Second tool', method: 'methodTwo')]
class MultiTool
{
    public function methodOne(string $input): string { }
    public function methodTwo(int $value): int { }
}
```

### 5. Maintain Conversation History
```php
// Keep the MessageBag and add to it
$history = new MessageBag(Message::forSystem('You are helpful'));

// First interaction
$history->add(Message::ofUser('My name is Alice'));
$result = $agent->call($history);
$history->add(Message::ofAssistant($result->getContent()));

// Second interaction (remembers Alice)
$history->add(Message::ofUser('What is my name?'));
$result = $agent->call($history);
```

---

## 🛠️ Common Tool Patterns

### Data Retrieval Tool
```php
#[AsTool('get_data', 'Fetches data from database')]
class DataFetcher
{
    public function __invoke(int $id): array
    {
        // Query database
        return ['id' => $id, 'data' => '...'];
    }
}
```

### Calculation Tool
```php
#[AsTool('calculate', 'Performs calculations')]
class Calculator
{
    public function __invoke(float $a, float $b, string $op): float
    {
        return match($op) {
            'add' => $a + $b,
            'multiply' => $a * $b,
            // ...
        };
    }
}
```

### External API Tool
```php
#[AsTool('search_web', 'Searches the web')]
class WebSearch
{
    public function __construct(
        private HttpClientInterface $client
    ) {}

    public function __invoke(string $query): array
    {
        $response = $this->client->request('GET', $apiUrl);
        return $response->toArray();
    }
}
```

### State Management Tool
```php
#[AsTool('save', 'Saves data', method: 'save')]
#[AsTool('load', 'Loads data', method: 'load')]
class Storage
{
    private array $data = [];

    public function save(string $key, string $value): string
    {
        $this->data[$key] = $value;
        return "Saved $key";
    }

    public function load(string $key): string
    {
        return $this->data[$key] ?? 'Not found';
    }
}
```

---

## 🎓 Learning Resources in This Project

### Your Practice Files
1. **`examples/my-first-agent.php`** - Start here!
   - Basic agent setup
   - Simple tools (calculator, database, time)
   - Interactive mode

2. **`examples/research-assistant-agent.php`** - Advanced
   - Conversation memory
   - Multiple specialized tools
   - Complex workflows

3. **`LEARNING_AI_AGENTS.md`** - Complete guide
   - Theory and concepts
   - Real-world examples
   - Best practices
   - Learning path

### Examples in the Project
- `examples/toolbox/` - Tool examples
- `examples/anthropic/toolcall.php` - Claude with tools
- `demo/src/` - Production examples

### Documentation
- `src/agent/doc/index.rst` - Full documentation
- `src/agent/README.md` - Component overview

---

## 🚀 How to Run Examples

### Run Your First Agent
```bash
cd /home/moeen/Documents/Stuff/Learning/projects/ai
php examples/my-first-agent.php
```

### Run Research Assistant
```bash
php examples/research-assistant-agent.php
```

### Run Other Examples
```bash
php examples/toolbox/clock.php
php examples/toolbox/weather-event.php
php examples/anthropic/toolcall.php
```

---

## 💡 Key Concepts

### 1. Tools are Discovered Automatically
The LLM receives a JSON Schema of all tools and decides when to use them based on:
- Tool name
- Tool description
- Parameter descriptions
- Current context

### 2. Tool Calling Flow
```
User Query
    ↓
Agent receives query
    ↓
LLM decides: "I need tool X"
    ↓
Tool X executes
    ↓
Result returned to LLM
    ↓
LLM formulates response
    ↓
Response to user
```

### 3. Multi-Step Reasoning
Agents can chain tools:
```
User: "Search for PHP and save what you find"
    ↓
Agent calls: search_tool('PHP')
    ↓
Agent calls: save_note('PHP is a programming language...')
    ↓
Agent responds: "I found information about PHP and saved it"
```

---

## 🔍 Debugging Tips

### Enable Logging
```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('agent');
$logger->pushHandler(new StreamHandler('php://stdout'));

$agent = new Agent($platform, $model, logger: $logger);
```

### Check Tool Calls
```php
// In your tool
public function __invoke($params): mixed
{
    error_log("Tool called with: " . json_encode($params));
    // ...
}
```

### Test Tools Independently
```php
// Before adding to agent, test directly
$tool = new MyTool();
$result = $tool('test input');
var_dump($result);
```

---

## 📊 Built-in Tools You Can Use

| Tool | Purpose | Import |
|------|---------|--------|
| Wikipedia | Search/fetch articles | `Symfony\AI\Agent\Toolbox\Tool\Wikipedia` |
| OpenMeteo | Weather data | `Symfony\AI\Agent\Toolbox\Tool\OpenMeteo` |
| SimilaritySearch | Vector search | `Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch` |
| Clock | Current time | `Symfony\Component\Clock\Clock` |
| Brave | Web search | `Symfony\AI\Agent\Toolbox\Tool\Brave` |
| Firecrawl | Web scraping | `Symfony\AI\Agent\Toolbox\Tool\Firecrawl` |

---

## 🎯 Practice Projects

### Beginner
- [ ] Calculator agent
- [ ] Time/date assistant
- [ ] Simple Q&A bot

### Intermediate
- [ ] Personal note-taking assistant
- [ ] Weather & news aggregator
- [ ] Code documentation helper

### Advanced
- [ ] Research assistant with memory
- [ ] Multi-agent system
- [ ] RAG-based chatbot
- [ ] Task automation agent

---

## ❓ Common Issues & Solutions

### "Tool not being called"
- ✅ Check tool description is clear
- ✅ Ensure parameters have doc comments
- ✅ Verify tool is added to toolbox
- ✅ Check system prompt guides tool usage

### "Agent returns incomplete response"
- ✅ Increase max tokens in model config
- ✅ Simplify system prompt
- ✅ Reduce number of tools

### "Tool error not handled"
- ✅ Add try-catch in tool method
- ✅ Return descriptive error messages
- ✅ Use FaultTolerantToolbox

---

## 🔗 Next Steps

1. ✅ Run `my-first-agent.php`
2. ✅ Modify the calculator tool
3. ✅ Add your own custom tool
4. ✅ Run `research-assistant-agent.php`
5. ✅ Study the demo app code
6. ✅ Build your own agent project
7. ✅ Explore other examples
8. ✅ Read the full documentation

---

## 📚 Vocabulary

- **Agent**: Orchestrator that combines LLM + Tools + Memory
- **Tool**: A function the agent can call
- **Toolbox**: Container for tools
- **Platform**: LLM provider (OpenAI, Anthropic, etc.)
- **Model**: Specific LLM (GPT-4, Claude, etc.)
- **Processor**: Middleware for input/output
- **MessageBag**: Container for conversation messages
- **RAG**: Retrieval-Augmented Generation
- **Embedding**: Vector representation of text
- **System Prompt**: Instructions for the agent's behavior

---

## 🎉 You Now Know

✅ What AI agents are and how they work
✅ How to create tools with `#[AsTool]`
✅ How to add tools to agents
✅ How to maintain conversation history
✅ How to build simple and complex agents
✅ Where to find more examples
✅ How to debug and test agents

**Now go build something amazing! 🚀**
