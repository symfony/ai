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
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

final class CategoryProvider implements SchemaProviderInterface
{
    /**
     * @param list<string> $categories
     */
    public function __construct(private readonly array $categories)
    {
    }

    public function getSchemaFragment(): array
    {
        return ['enum' => $this->categories];
    }
}

final class IssueQuery
{
    /**
     * @param string $category Category of the issue
     */
    public function __construct(
        #[SchemaSource(CategoryProvider::class)]
        public readonly string $category,
    ) {
    }
}

$providers = new class([CategoryProvider::class => new CategoryProvider(['bug', 'feature', 'docs', 'chore'])]) implements ContainerInterface {
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

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber(new ResponseFormatFactory($schemaFactory)));

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);
$messages = new MessageBag(
    Message::forSystem('Classify the user request into a category.'),
    Message::ofUser('I found a typo in the README.'),
);

$result = $platform->invoke('gpt-5-mini', $messages, ['response_format' => IssueQuery::class]);

dump($result->asObject());
