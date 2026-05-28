<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Mock\MockModelClient;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;

final class MockModelClientTest extends TestCase
{
    public function testSupportsReturnsTrueForAnyModel()
    {
        $client = new MockModelClient('answer');

        $this->assertTrue($client->supports(new Model('any-model')));
    }

    public function testStringResponseWrapsInTextResult()
    {
        $client = new MockModelClient('Hello world');

        $rawResult = $client->request(new Model('any-model'), 'question');
        $result = $rawResult->getObject();

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testMapResponseSelectsByModelName()
    {
        $client = new MockModelClient([
            'model-a' => 'answer a',
            'model-b' => new ObjectResult(['foo' => 'bar']),
        ]);

        $resultA = $client->request(new Model('model-a'), 'q')->getObject();
        $resultB = $client->request(new Model('model-b'), 'q')->getObject();

        $this->assertInstanceOf(TextResult::class, $resultA);
        $this->assertSame('answer a', $resultA->getContent());
        $this->assertInstanceOf(ObjectResult::class, $resultB);
        $this->assertSame(['foo' => 'bar'], $resultB->getContent());
    }

    public function testMapResponseThrowsForUnknownModel()
    {
        $client = new MockModelClient(['known' => 'answer']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No fake response configured for model "unknown".');

        $client->request(new Model('unknown'), 'q');
    }

    public function testClosureReceivesModelPayloadAndOptions()
    {
        $client = new MockModelClient(static function (Model $model, array|string $payload, array $options): string {
            return \sprintf('%s|%s|%s', $model->getName(), $payload, $options['stream'] ? 'stream' : 'sync');
        });

        $result = $client->request(new Model('gpt'), 'hi', ['stream' => true])->getObject();

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('gpt|hi|stream', $result->getContent());
    }

    public function testGetCallsRecordsEveryInvocation()
    {
        $client = new MockModelClient('answer');

        $client->request(new Model('model-a'), 'first', ['temperature' => 0.5]);
        $client->request(new Model('model-b'), ['messages' => []]);

        $calls = $client->getCalls();

        $this->assertCount(2, $calls);
        $this->assertSame('model-a', $calls[0]['model']->getName());
        $this->assertSame('first', $calls[0]['payload']);
        $this->assertSame(['temperature' => 0.5], $calls[0]['options']);
        $this->assertSame('model-b', $calls[1]['model']->getName());
        $this->assertSame(['messages' => []], $calls[1]['payload']);
        $this->assertSame([], $calls[1]['options']);
    }
}
