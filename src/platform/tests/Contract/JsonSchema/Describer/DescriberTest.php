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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\CPPWithAtVarDocFixture;

final class DescriberTest extends TestCase
{
    public function testDescribeObject()
    {
        $describer = new Describer();
        $describer->describeObject(new ObjectSubject(CPPWithAtVarDocFixture::class, new \ReflectionClass(CPPWithAtVarDocFixture::class)), $actual);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'explanation' => [
                                'type' => 'string',
                            ],
                            'output' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['explanation', 'output'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], $actual);
    }

    public function testDescribeObjectRunsPropertyDescribersInOrderAcrossPropertySubjects()
    {
        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $object = new class('hello') {
            public function __construct(public ?string $note)
            {
            }
        };

        $objectDescriber = new class implements ObjectDescriberInterface {
            public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
            {
                $class = $subject->getName();
                yield new PropertySubject('note', new \ReflectionProperty($class, 'note'));
                yield new PropertySubject('note', new \ReflectionParameter([$class, '__construct'], 'note'));
            }
        };

        $firstPropertyDescriber = new class($log) implements PropertyDescriberInterface {
            /**
             * @param \ArrayObject<int, string> $log
             */
            public function __construct(private readonly \ArrayObject $log)
            {
            }

            /**
             * @param array<string, mixed>|null $schema
             *
             * @param-out array<string, mixed> $schema
             */
            public function describeProperty(PropertySubject $subject, ?array &$schema): void
            {
                $this->log->append('first:'.$subject->getReflector()::class);
                $schema['type'] = ['string', 'null'];
            }
        };

        $secondPropertyDescriber = new class($log) implements PropertyDescriberInterface {
            /**
             * @param \ArrayObject<int, string> $log
             */
            public function __construct(private readonly \ArrayObject $log)
            {
            }

            public function describeProperty(PropertySubject $subject, ?array &$schema): void
            {
                $this->log->append('second:'.$subject->getReflector()::class);
                if ($subject->getReflector() instanceof \ReflectionProperty) {
                    $schema['type'] = 'string';
                }
            }
        };

        $describer = new Describer([$objectDescriber, $firstPropertyDescriber, $secondPropertyDescriber]);
        $describer->describeObject(new ObjectSubject($object::class, new \ReflectionClass($object)), $actual);

        $this->assertSame([
            'first:ReflectionProperty',
            'first:ReflectionParameter',
            'second:ReflectionProperty',
            'second:ReflectionParameter',
        ], $log->getArrayCopy());
        $this->assertSame('string', $actual['properties']['note']['type']);
    }
}
