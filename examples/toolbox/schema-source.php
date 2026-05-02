<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\MethodDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaSourceDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory as SchemaFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('search_issues', 'Search issues by status')]
final class IssueSearchTool
{
    public function __invoke(
        #[SchemaSource(StatusProvider::class)]
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

    public function getSchemaFragment(): array
    {
        return ['enum' => $this->statuses];
    }
}

$providers = new class([StatusProvider::class => new StatusProvider(['open', 'in_progress', 'closed'])]) implements ContainerInterface {
    /**
     * @param array<class-string<SchemaProviderInterface>, SchemaProviderInterface> $services
     */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): SchemaProviderInterface
    {
        return $this->services[$id] ?? throw new class("Service \"$id\" not found.") extends RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
};

$schemaFactory = new SchemaFactory(new Describer([
    new SerializerDescriber(),
    new TypeInfoDescriber(),
    new MethodDescriber(),
    new PropertyInfoDescriber(),
    new SchemaAttributeDescriber(),
    new SchemaSourceDescriber($providers),
]));

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$toolbox = new Toolbox([new IssueSearchTool()], new ReflectionToolFactory($schemaFactory), logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('Search for closed issues'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
