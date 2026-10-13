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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
use Symfony\AI\Platform\Contract\JsonSchema\Factory as SchemaFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('search_issues', 'Search issues by status')]
final class IssueSearchTool
{
    public function __invoke(
        #[Schema(provider: StatusProvider::class)]
        string $status,
    ): string {
        return sprintf('Found 3 issues with status="%s".', $status);
    }
}

final class StatusProvider implements SchemaProviderInterface
{
    /**
     * @param list<string> $statuses
     */
    public function __construct(private readonly array $statuses)
    {
    }

    public function getSchemaFragment(array $context = []): array
    {
        return ['enum' => $this->statuses];
    }
}

$schemaFactory = new SchemaFactory([
    new SchemaAttributeEnricher([
        StatusProvider::class => new StatusProvider(['open', 'in_progress', 'closed']),
    ]),
]);

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$toolbox = new Toolbox([new IssueSearchTool()], new ReflectionToolFactory($schemaFactory), logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('Search for closed issues'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;
