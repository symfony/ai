<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cache\ResultNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class ResultNormalizerTest extends TestCase
{
    private NormalizerInterface $normalizer;

    protected function setUp(): void
    {
        $resultNormalizer = new ResultNormalizer(new ObjectNormalizer());
        $toolCallNormalizer = new ToolCallNormalizer();

        $this->normalizer = new Serializer([$toolCallNormalizer, $resultNormalizer]);
    }

    public function testNormalizerSupport()
    {
        $result = $this->createMock(ResultInterface::class);

        $normalizer = new ResultNormalizer(new ObjectNormalizer());

        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertTrue($normalizer->supportsNormalization($result));
        $this->assertFalse($normalizer->supportsDenormalization(null, \stdClass::class));
        $this->assertTrue($normalizer->supportsDenormalization(null, ResultInterface::class));
        $this->assertSame([
            ResultInterface::class => true,
        ], $normalizer->getSupportedTypes(''));
    }

    public function testNormalizerCannotNormalizeStreamResult()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" cannot be normalized.', StreamResult::class));
        $this->expectExceptionCode(0);
        $this->normalizer->normalize(new StreamResult((static fn (): \Generator => yield from [])()));
    }

    /**
     * @param array{
     *     class: string,
     *     payload: array<string, mixed>,
     * } $expectedOutput
     */
    #[DataProvider('provideResultForNormalization')]
    public function testNormalizerCanNormalize(ResultInterface $result, array $expectedOutput)
    {
        $this->assertSame($expectedOutput, $this->normalizer->normalize($result));
    }

    /**
     * @param array{
     *     class: string,
     *     payload: array<string, mixed>,
     * } $expectedOutput
     */
    #[DataProvider('provideResultForNormalization')]
    public function testNormalizerCanDenormalize(ResultInterface $result, array $expectedOutput)
    {
        $resultNormalizer = new ResultNormalizer(new ObjectNormalizer());

        $this->assertEquals($result, $resultNormalizer->denormalize($expectedOutput, ResultInterface::class));
    }

    public static function provideResultForNormalization(): \Generator
    {
        yield BinaryResult::class => [
            new BinaryResult('foo'),
            [
                'class' => BinaryResult::class,
                'payload' => [
                    'asBase64' => base64_encode('foo'),
                    'mimeType' => null,
                ],
            ],
        ];
        yield ChoiceResult::class => [
            new ChoiceResult([
                new TextResult('foo'),
                new TextResult('bar'),
            ]),
            [
                'class' => ChoiceResult::class,
                'payload' => [
                    [
                        'class' => TextResult::class,
                        'payload' => 'foo',
                    ],
                    [
                        'class' => TextResult::class,
                        'payload' => 'bar',
                    ],
                ],
            ],
        ];
        yield ObjectResult::class.'-array' => [
            new ObjectResult([
                'foo' => 'bar',
            ]),
            [
                'class' => ObjectResult::class,
                'payload' => [
                    'type' => 'array',
                    'content' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
        yield ObjectResult::class.'-object' => [
            new ObjectResult(new \stdClass()),
            [
                'class' => ObjectResult::class,
                'payload' => [
                    'type' => 'stdClass',
                    'content' => [],
                ],
            ],
        ];
        yield TextResult::class => [
            new TextResult('foo'),
            [
                'class' => TextResult::class,
                'payload' => 'foo',
            ],
        ];
        yield ToolCallResult::class => [
            new ToolCallResult([
                new ToolCall('call-1', 'get_weather', ['city' => 'Paris']),
            ]),
            [
                'class' => ToolCallResult::class,
                'payload' => [
                    [
                        'id' => 'call-1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Paris"}',
                        ],
                    ],
                ],
            ],
        ];
        yield VectorResult::class => [
            new VectorResult([new Vector([0.1, 0.2, 0.3])]),
            [
                'class' => VectorResult::class,
                'payload' => [
                    [
                        'data' => [0.1, 0.2, 0.3],
                        'dimensions' => 3,
                    ],
                ],
            ],
        ];
    }
}
