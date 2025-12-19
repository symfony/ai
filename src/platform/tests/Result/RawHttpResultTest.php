<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RawHttpResultTest extends TestCase
{
    public function testGetData()
    {
        $responseData = ['key' => 'value', 'nested' => ['foo' => 'bar']];
        $response = new MockResponse(json_encode($responseData));

        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $rawResult = new RawHttpResult($actualResponse);

        $this->assertSame($responseData, $rawResult->getData());
    }

    public function testGetObject()
    {
        $response = new MockResponse('{"key": "value"}');

        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $rawResult = new RawHttpResult($actualResponse);

        $this->assertInstanceOf(ResponseInterface::class, $rawResult->getObject());
        $this->assertSame($actualResponse, $rawResult->getObject());
    }

    public function testGetDataWithEmptyResponse()
    {
        $response = new MockResponse('{}');

        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $rawResult = new RawHttpResult($actualResponse);

        $this->assertSame([], $rawResult->getData());
    }

    public function testGetDataWithArrayResponse()
    {
        $responseData = [
            'choices' => [
                ['message' => ['content' => 'Hello']],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
            ],
        ];
        $response = new MockResponse(json_encode($responseData));

        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $rawResult = new RawHttpResult($actualResponse);

        $this->assertSame($responseData, $rawResult->getData());
    }
}
