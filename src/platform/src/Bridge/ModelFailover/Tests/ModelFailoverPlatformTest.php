<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelFailover\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\ModelFailover\ModelFailoverPlatform;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class ModelFailoverPlatformTest extends TestCase
{
    public function testPlatformCannotBeCreatedWithoutModels()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\AI\Platform\Bridge\ModelFailover\ModelFailoverPlatform" must have at least one model configured.');
        $this->expectExceptionCode(0);
        new ModelFailoverPlatform(
            new InMemoryPlatform(static fn (): string => 'foo'),
            [], // @phpstan-ignore argument.type (testing runtime validation)
        );
    }

    public function testInvokeSucceedsWithRequestedModel()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')
            ->with('primary-model', 'input', [])
            ->willReturn($this->createDeferredResult('success'));

        $modelFailover = new ModelFailoverPlatform($platform, ['fallback-model']);

        $result = $modelFailover->invoke('primary-model', 'input');

        $this->assertSame('success', $result->asText());
    }

    public function testInvokeFallsBackToNextModelOnFailure()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')
            ->willReturnCallback(static function (string $model) {
                if ('primary-model' === $model) {
                    throw new \Exception('Primary model unavailable.');
                }

                return (new InMemoryPlatform(static fn (): string => 'fallback-result'))->invoke($model, 'input');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $modelFailover = new ModelFailoverPlatform($platform, ['fallback-model'], $logger);

        $result = $modelFailover->invoke('primary-model', 'input');

        $this->assertSame('fallback-result', $result->asText());
    }

    public function testInvokeTriesAllModelsBeforeFailing()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(3))->method('invoke')
            ->willThrowException(new \Exception('Model unavailable.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('error');

        $modelFailover = new ModelFailoverPlatform($platform, ['fallback-a', 'fallback-b'], $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All models failed on platform');
        $this->expectExceptionCode(0);
        $modelFailover->invoke('primary-model', 'input');
    }

    public function testRequestedModelIsNotDuplicatedInFallbackList()
    {
        $invokedModels = [];

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')
            ->willReturnCallback(static function (string $model) use (&$invokedModels) {
                $invokedModels[] = $model;

                if ('primary-model' === $model) {
                    throw new \Exception('Primary model unavailable.');
                }

                return (new InMemoryPlatform(static fn (): string => 'ok'))->invoke($model, 'input');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        // primary-model is also in the fallback list — should not be tried twice
        $modelFailover = new ModelFailoverPlatform($platform, ['primary-model', 'fallback-model'], $logger);

        $result = $modelFailover->invoke('primary-model', 'input');

        $this->assertSame('ok', $result->asText());
        $this->assertSame(['primary-model', 'fallback-model'], $invokedModels);
    }

    public function testInvokeFallsBackWhenDeferredResultFails()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')
            ->willReturnCallback(function (string $model): \Symfony\AI\Platform\Result\DeferredResult {
                if ('primary-model' === $model) {
                    // invoke() succeeds, but getResult() will throw — simulates
                    // a model-level error that only surfaces during evaluation
                    return $this->createFailingDeferredResult('Model rate limited.');
                }

                return $this->createDeferredResult('fallback-result');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $modelFailover = new ModelFailoverPlatform($platform, ['fallback-model'], $logger);

        $result = $modelFailover->invoke('primary-model', 'input');

        $this->assertSame('fallback-result', $result->asText());
    }

    public function testGetModelCatalogDelegatesToPlatform()
    {
        $platform = new InMemoryPlatform(static fn (): string => 'foo');

        $modelFailover = new ModelFailoverPlatform($platform, ['model-a']);

        $this->assertInstanceOf(FallbackModelCatalog::class, $modelFailover->getModelCatalog());
    }

    public function testOptionsArePassedThrough()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')
            ->with('model-a', 'input', ['temperature' => 0.5])
            ->willReturn($this->createDeferredResult('ok'));

        $modelFailover = new ModelFailoverPlatform($platform, ['model-a']);

        $result = $modelFailover->invoke('model-a', 'input', ['temperature' => 0.5]);

        $this->assertSame('ok', $result->asText());
    }

    private function createDeferredResult(string $text): \Symfony\AI\Platform\Result\DeferredResult
    {
        return new \Symfony\AI\Platform\Result\DeferredResult(
            new \Symfony\AI\Platform\PlainConverter(new \Symfony\AI\Platform\Result\TextResult($text)),
            new \Symfony\AI\Platform\Result\InMemoryRawResult(['text' => $text]),
        );
    }

    private function createFailingDeferredResult(string $errorMessage): \Symfony\AI\Platform\Result\DeferredResult
    {
        $converter = new class($errorMessage) implements \Symfony\AI\Platform\ResultConverterInterface {
            public function __construct(private readonly string $message)
            {
            }

            public function supports(\Symfony\AI\Platform\Model $model): bool
            {
                return true;
            }

            public function convert(\Symfony\AI\Platform\Result\RawResultInterface $rawResult, array $options = []): \Symfony\AI\Platform\Result\ResultInterface
            {
                throw new \RuntimeException($this->message);
            }

            public function getTokenUsageExtractor(): ?\Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface
            {
                return null;
            }
        };

        return new \Symfony\AI\Platform\Result\DeferredResult(
            $converter,
            new \Symfony\AI\Platform\Result\InMemoryRawResult([]),
        );
    }
}
