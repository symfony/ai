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
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\Filterable;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\OrderFilter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\PurchaseContractFilter;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

require_once dirname(__DIR__).'/bootstrap.php';

// Setup and run
$platforms = [
    'claude-sonnet-4-5-20250929' => AnthropicPlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client()),
    'gpt-5-mini' => OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client()),
    'gemini-2.5-flash' => GeminiPlatformFactory::create(env('GEMINI_API_KEY'), httpClient: http_client()),
];

foreach ($platforms as $model => $platform) {
    $searchTool = new SearchTool();
    $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
    $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

    $toolArgNormalizers = [
        new DateTimeNormalizer(),
        new ObjectNormalizer(
            classMetadataFactory: $classMetadataFactory,
            classDiscriminatorResolver: $discriminator
        ),
        new ArrayDenormalizer(),
    ];
    $toolbox = new Toolbox(
        tools: [$searchTool],
        argumentResolver: new ToolCallArgumentResolver(new Serializer($toolArgNormalizers))
    );
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
