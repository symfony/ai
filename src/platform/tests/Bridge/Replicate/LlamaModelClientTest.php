<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Replicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\AI\Platform\Bridge\Replicate\LlamaModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(LlamaModelClient::class)]
final class LlamaModelClientTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $httpClient = new MockHttpClient();
        $client = new Client($httpClient, new MockClock(), 'test-key');
        $modelClient = new LlamaModelClient($client);

        $this->assertTrue($modelClient->supports(new Llama('llama-3.1-405b-instruct')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $httpClient = new MockHttpClient();
        $client = new Client($httpClient, new MockClock(), 'test-key');
        $modelClient = new LlamaModelClient($client);

        $otherModel = $this->createMock(Model::class);
        $this->assertFalse($modelClient->supports($otherModel));
    }

    public function testRequestWithLlamaModel()
    {
        $mockResponse = new MockResponse('{"status": "succeeded"}');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new Client($httpClient, new MockClock(), 'test-key');

        $modelClient = new LlamaModelClient($client);
        $result = $modelClient->request(new Llama('llama-3.1-405b-instruct'), ['prompt' => 'Hello']);

        $this->assertInstanceOf(RawHttpResult::class, $result);
    }

    public function testRequestThrowsExceptionForUnsupportedModel()
    {
        $modelClient = new LlamaModelClient(new Client(new MockHttpClient(), new MockClock(), 'test-key'));
        $otherModel = $this->createMock(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model must be an instance of "Symfony\AI\Platform\Bridge\Meta\Llama".');

        $modelClient->request($otherModel, ['prompt' => 'Hello']);
    }
}
