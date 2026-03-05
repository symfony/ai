<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\Filterable;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\OrderFilter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\PurchaseContractFilter;

require_once dirname(__DIR__).'/bootstrap.php';

$platforms = [
    'claude-sonnet-4-5-20250929' => AnthropicPlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client()),
    'gpt-5-mini' => OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client()),
    'gemini-2.5-flash' => GeminiPlatformFactory::create(env('GEMINI_API_KEY'), httpClient: http_client()),
];

foreach ($platforms as $model => $platform) {
    $toolbox = new Toolbox(tools: [new SearchTool()]);
    $processor = new AgentProcessor(toolbox: $toolbox);
    $agent = new Agent($platform, $model, [$processor], [$processor]);

    echo "\n=== Testing with model: $model ===\n\n";

    $systemMsg = Message::forSystem('You are a helpful search assistant. Help users search for different resources.');
    $userMsg = 'Search for order number ORD-12345 with user responsible John Doe';
    $messages = new MessageBag(
        $systemMsg,
        Message::ofUser($userMsg)
    );

    $result = $agent->call($messages);

    echo $userMsg." : \n".$result->getContent().\PHP_EOL.\PHP_EOL;

    $userMsg = 'Search for purchase contract number PC-67890 with subsidiary Acme Corp';
    $messages = new MessageBag(
        $systemMsg,
        Message::ofUser($userMsg),
    );

    $result = $agent->call($messages);

    echo $userMsg." : \n".$result->getContent().\PHP_EOL;
}

// Tool that uses polymorphic interface as parameter
#[AsTool('search', 'Search various resources based on filter criteria', method: 'search')]
final class SearchTool
{
    /**
     * @param Filterable $filter The filter to use for search (order or purchase_contract)
     */
    public function search(Filterable $filter): string
    {
        return match (true) {
            $filter instanceof OrderFilter => sprintf(
                'Searching for order: %s (User: %s) Found 1 result.',
                $filter->number ?? 'N/A',
                $filter->userResponsible ?? 'N/A'
            ),
            $filter instanceof PurchaseContractFilter => sprintf(
                'Searching for purchase contract: %s (Subsidiary: %s) Found 1 result.',
                $filter->contractNumber ?? 'N/A',
                $filter->subsidiary ?? 'N/A'
            ),
            default => 'Unknown filter type',
        };
    }
}
