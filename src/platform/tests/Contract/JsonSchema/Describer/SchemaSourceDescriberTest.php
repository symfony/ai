<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaSourceDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ColorProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ContextAwareProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\SearchQueryDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;

final class SchemaSourceDescriberTest extends TestCase
{
    public function testMergesFragmentWithContext()
    {
        $describer = new SchemaSourceDescriber($this->container([
            ContextAwareProvider::class => new ContextAwareProvider(),
        ]));

        $subject = new PropertySubject('category', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'category'));
        $schema = ['type' => 'string'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['foo', 'bar']], $schema);
    }

    public function testMergesFragmentFromProviderOnParameter()
    {
        $describer = new SchemaSourceDescriber($this->container([
            StatusProvider::class => new StatusProvider(['active', 'archived']),
            ColorProvider::class => new ColorProvider(['red', 'blue']),
        ]));

        $subject = new PropertySubject('status', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status'));
        $schema = ['type' => 'string', 'description' => 'pre-existing'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame([
            'type' => 'string',
            'description' => 'pre-existing',
            'enum' => ['active', 'archived'],
        ], $schema);
    }

    public function testMergesFragmentFromProviderOnProperty()
    {
        $describer = new SchemaSourceDescriber($this->container([
            ColorProvider::class => new ColorProvider(['red', 'blue']),
        ]));

        $subject = new PropertySubject('color', new \ReflectionProperty(SearchQueryDto::class, 'color'));
        $schema = ['type' => 'string'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['red', 'blue']], $schema);
    }

    public function testNoOpWhenNoAttributePresent()
    {
        $describer = new SchemaSourceDescriber($this->container([]));

        $subject = new PropertySubject('query', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'query'));
        $schema = ['type' => 'string', 'minLength' => 3];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'minLength' => 3], $schema);
    }

    public function testThrowsWhenProviderIsNotRegistered()
    {
        $describer = new SchemaSourceDescriber($this->container([]));

        $subject = new PropertySubject('status', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status'));
        $schema = ['type' => 'string'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SchemaSource "'.StatusProvider::class.'" is not registered.');

        $describer->describeProperty($subject, $schema);
    }

    /**
     * @param array<class-string<SchemaProviderInterface>, SchemaProviderInterface> $services
     */
    private function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /**
             * @param array<class-string, SchemaProviderInterface> $services
             */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): SchemaProviderInterface
            {
                if (!isset($this->services[$id])) {
                    throw new class('Service '.$id.' not found.') extends \RuntimeException implements NotFoundExceptionInterface {};
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}
