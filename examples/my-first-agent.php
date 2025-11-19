<?php

/*
 * Simple AI Agent Example - Your First Agent
 * 
 * This example demonstrates:
 * 1. Creating a basic agent
 * 2. Adding a custom tool
 * 3. Using the agent to solve problems
 */

require_once __DIR__.'/bootstrap.php';

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

// ===================================
// STEP 1: Create Custom Tools
// ===================================

/**
 * A simple calculator tool that the agent can use
 */
#[AsTool('calculate', description: 'Performs mathematical calculations')]
class Calculator
{
    /**
     * @param float $a First number
     * @param float $b Second number  
     * @param string $operation The operation to perform: add, subtract, multiply, or divide
     */
    public function __invoke(float $a, float $b, string $operation): float
    {
        return match($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Cannot divide by zero'),
            default => throw new \InvalidArgumentException("Unknown operation: $operation"),
        };
    }
}

/**
 * A tool to get user information (simulated database)
 */
#[AsTool('get_user', description: 'Retrieves user information from the database', method: 'getUser')]
#[AsTool('list_users', description: 'Lists all users in the database', method: 'listUsers')]
class UserDatabase
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice Smith', 'email' => 'alice@example.com', 'role' => 'developer'],
        2 => ['id' => 2, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'role' => 'designer'],
        3 => ['id' => 3, 'name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'role' => 'manager'],
    ];

    /**
     * @param int $userId The ID of the user to retrieve
     */
    public function getUser(int $userId): array
    {
        if (!isset($this->users[$userId])) {
            return ['error' => "User with ID $userId not found"];
        }
        return $this->users[$userId];
    }

    public function listUsers(): array
    {
        return array_values($this->users);
    }
}

/**
 * A tool to get current time and date
 */
#[AsTool('current_time', description: 'Gets the current date and time')]
class TimeTool
{
    /**
     * @param string $format Optional format string (default: Y-m-d H:i:s)
     */
    public function __invoke(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

// ===================================
// STEP 2: Setup the Agent
// ===================================

echo "🤖 Creating AI Agent with Custom Tools...\n\n";

// Initialize OpenAI platform
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI);

// Create tools
$calculator = new Calculator();
$userDb = new UserDatabase();
$timeTool = new TimeTool();

// Create toolbox with all tools
$toolbox = new Toolbox([$calculator, $userDb, $timeTool], logger: logger());
$processor = new AgentProcessor($toolbox);

// Create the agent with tools
$agent = new Agent(
    $platform, 
    $model, 
    inputProcessors: [$processor],
    outputProcessors: [$processor],
    logger: logger()
);

echo "✅ Agent created with 3 tools:\n";
echo "   - Calculator (add, subtract, multiply, divide)\n";
echo "   - User Database (get user info, list users)\n";
echo "   - Time Tool (get current time)\n\n";

// ===================================
// STEP 3: Test the Agent
// ===================================

$testQueries = [
    "What is 25 multiplied by 4?",
    "Can you tell me who user 2 is?",
    "What's the current date and time?",
    "Calculate 100 divided by 5, then add 10 to the result",
    "List all users and tell me who is a developer",
];

echo "🧪 Testing Agent with Various Queries:\n";
echo str_repeat("=", 60)."\n\n";

foreach ($testQueries as $index => $query) {
    echo "Query ".($index + 1).": $query\n";
    echo str_repeat("-", 60)."\n";
    
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant with access to tools. Use them when needed to answer questions accurately.'),
        Message::ofUser($query)
    );
    
    try {
        $result = $agent->call($messages);
        echo "Response: ".$result->getContent()."\n\n";
    } catch (\Exception $e) {
        echo "Error: ".$e->getMessage()."\n\n";
    }
    
    sleep(1); // Be nice to the API
}

echo str_repeat("=", 60)."\n";
echo "✅ All tests completed!\n\n";

// ===================================
// STEP 4: Interactive Mode (Optional)
// ===================================

echo "💬 Interactive Mode (Ctrl+C to exit)\n";
echo str_repeat("=", 60)."\n\n";

while (true) {
    echo "You: ";
    $input = trim(fgets(STDIN));
    
    if (empty($input)) {
        continue;
    }
    
    if (in_array(strtolower($input), ['exit', 'quit', 'bye'])) {
        echo "👋 Goodbye!\n";
        break;
    }
    
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant with access to tools. Use them when needed.'),
        Message::ofUser($input)
    );
    
    try {
        $result = $agent->call($messages);
        echo "Agent: ".$result->getContent()."\n\n";
    } catch (\Exception $e) {
        echo "Error: ".$e->getMessage()."\n\n";
    }
}
