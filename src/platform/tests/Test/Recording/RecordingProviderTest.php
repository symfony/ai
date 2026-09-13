<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test\Recording;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockPlatformFactory as MockFactory;
use Symfony\AI\Platform\Test\Recording\Cassette;
use Symfony\AI\Platform\Test\Recording\RecordingProvider;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class RecordingProviderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/ai-recording-provider-'.bin2hex(random_bytes(6)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testRecordsResultToCassette()
    {
        $provider = new RecordingProvider(
            MockFactory::createProvider('hello'),
            new Cassette($this->path),
            record: true,
        );

        $result = $provider->invoke('any-model', 'q');

        $this->assertSame('hello', $result->asText());
        $this->assertFileExists($this->path);
    }

    public function testReplaysWithoutInvokingInnerProvider()
    {
        $recorder = new RecordingProvider(MockFactory::createProvider('hello'), new Cassette($this->path), record: true);
        $recorder->invoke('any-model', 'q');

        $replay = new RecordingProvider($this->throwingProvider(), new Cassette($this->path), record: false);

        $this->assertSame('hello', $replay->invoke('any-model', 'q')->asText());
    }

    public function testRecordsAutomaticallyWhenCassetteMissing()
    {
        $provider = new RecordingProvider(MockFactory::createProvider('hello'), new Cassette($this->path));

        $this->assertSame('hello', $provider->invoke('any-model', 'q')->asText());
        $this->assertFileExists($this->path);
    }

    public function testReplaysAutomaticallyWhenCassetteExists()
    {
        $recorder = new RecordingProvider(MockFactory::createProvider('hello'), new Cassette($this->path));
        $recorder->invoke('any-model', 'q');

        $replay = new RecordingProvider($this->throwingProvider(), new Cassette($this->path));

        $this->assertSame('hello', $replay->invoke('any-model', 'q')->asText());
    }

    public function testReplaysObjectResult()
    {
        $this->record(MockFactory::createProvider(static fn (): ObjectResult => new ObjectResult((object) ['answer' => 42])));

        $object = $this->replay()->invoke('any-model', 'q')->asObject();

        $this->assertIsObject($object);
        $this->assertSame(42, $object->answer);
    }

    public function testReplaysVectorResult()
    {
        $this->record(MockFactory::createProvider(static fn (): VectorResult => new VectorResult([new Vector([0.1, 0.2, 0.3])])));

        $vectors = $this->replay()->invoke('any-model', 'q')->asVectors();

        $this->assertSame([0.1, 0.2, 0.3], $vectors[0]->getData());
    }

    public function testReplaysToolCallResult()
    {
        $this->record(MockFactory::createProvider(static fn (): ToolCallResult => new ToolCallResult([
            new ToolCall('id-1', 'get_weather', ['location' => 'Paris']),
        ])));

        $toolCalls = $this->replay()->invoke('any-model', 'q')->asToolCalls();

        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());
    }

    public function testReplaysTextStreamResult()
    {
        $this->record(MockFactory::createProvider(static fn (): StreamResult => new StreamResult((static function (): \Generator {
            yield new TextDelta('Hel');
            yield new TextDelta('lo');
        })())));

        $text = '';
        foreach ($this->replay()->invoke('any-model', 'q')->asTextStream() as $delta) {
            $text .= (string) $delta;
        }

        $this->assertSame('Hello', $text);
    }

    public function testRecordRunsRealBridgeConverterThenReplayServesItOffline()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [['type' => 'text', 'text' => 'Recorded by Anthropic']],
        ]));

        $recorder = new RecordingProvider(
            AnthropicFactory::createProvider('test-key', $httpClient),
            new Cassette($this->path),
            record: true,
        );

        $messages = new MessageBag(Message::ofUser('Hello'));

        $this->assertSame('Recorded by Anthropic', $recorder->invoke('claude-3-7-sonnet-20250219', $messages)->asText());

        $replay = new RecordingProvider($this->throwingProvider(), new Cassette($this->path), record: false);

        $this->assertSame('Recorded by Anthropic', $replay->invoke('claude-3-7-sonnet-20250219', new MessageBag(Message::ofUser('Hello')))->asText());
    }

    public function testReplayedResultExposesRecordedTokenUsage()
    {
        $this->record(MockFactory::createProvider(static function (): TextResult {
            $result = new TextResult('hello');
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 12, completionTokens: 34, totalTokens: 46));

            return $result;
        }));

        $result = $this->replay()->invoke('any-model', 'q');

        $this->assertSame('hello', $result->asText());

        $usage = $result->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertSame(12, $usage->getPromptTokens());
        $this->assertSame(34, $usage->getCompletionTokens());
        $this->assertSame(46, $usage->getTotalTokens());
    }

    public function testReplayedStreamExposesRecordedTokenUsage()
    {
        // No listener is wired here on purpose: DeferredResult attaches the token usage stream
        // listener itself, which is what promotes the delta to result metadata at record time.
        $this->record(MockFactory::createProvider(static fn (): StreamResult => new StreamResult((static function (): \Generator {
            yield new TextDelta('Hel');
            yield new TokenUsage(promptTokens: 4, completionTokens: 2, totalTokens: 6);
            yield new TextDelta('lo');
        })())));

        $result = $this->replay()->invoke('any-model', 'q');

        $text = '';
        foreach ($result->asTextStream() as $delta) {
            $text .= (string) $delta;
        }

        $usage = $result->getMetadata()->get('token_usage');

        $this->assertSame('Hello', $text);
        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertSame(6, $usage->getTotalTokens());
    }

    public function testReplayedResultPreservesFloatMetadataThroughTheCassetteFile()
    {
        $this->record(MockFactory::createProvider(static function (): TextResult {
            $result = new TextResult('hello');
            $result->getMetadata()->add('usage', ['seconds' => 2.0, 'ratio' => 1.5, 'score' => 0.0]);

            return $result;
        }));

        $result = $this->replay()->invoke('any-model', 'q');

        $this->assertSame('hello', $result->asText());
        $this->assertSame(['seconds' => 2.0, 'ratio' => 1.5, 'score' => 0.0], $result->getMetadata()->get('usage'));
    }

    public function testReplayThrowsOnSignatureMiss()
    {
        $recorder = new RecordingProvider(MockFactory::createProvider('hello'), new Cassette($this->path), record: true);
        $recorder->invoke('any-model', 'q');

        $replay = new RecordingProvider($this->throwingProvider(), new Cassette($this->path), record: false);

        $this->expectException(RuntimeException::class);
        $replay->invoke('any-model', 'different-input');
    }

    public function testCustomSignatureMatchesDespiteVaryingInput()
    {
        $signature = static fn (string|Model $model, array|string|object $input, array $options): string => 'fixed';

        $recorder = new RecordingProvider(MockFactory::createProvider('hello'), new Cassette($this->path), record: true, signature: $signature);
        $recorder->invoke('any-model', 'question asked at 2026-01-01');

        $replay = new RecordingProvider($this->throwingProvider(), new Cassette($this->path), record: false, signature: $signature);

        $this->assertSame('hello', $replay->invoke('any-model', 'question asked at 2026-08-16')->asText());
    }

    public function testDelegatesMetadataMethods()
    {
        $inner = MockFactory::createProvider('hello', name: 'inner');
        $provider = new RecordingProvider($inner, new Cassette($this->path), record: false);

        $this->assertSame('inner', $provider->getName());
        $this->assertTrue($provider->supports('any-model'));
        $this->assertSame($inner->getModelCatalog(), $provider->getModelCatalog());
    }

    private function record(ProviderInterface $inner): void
    {
        $provider = new RecordingProvider($inner, new Cassette($this->path), record: true);
        $provider->invoke('any-model', 'q');
    }

    private function replay(): RecordingProvider
    {
        return new RecordingProvider($this->throwingProvider(), new Cassette($this->path), record: false);
    }

    private function throwingProvider(): ProviderInterface
    {
        return new class implements ProviderInterface {
            public function getName(): string
            {
                return 'throwing';
            }

            public function supports(string|Model $model): bool
            {
                return true;
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new RuntimeException('Inner provider must not be touched on replay.');
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                throw new RuntimeException('Inner provider must not be invoked on replay.');
            }
        };
    }
}
