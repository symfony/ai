<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\Meta;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Meta\LlamaResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LlamaResultConverterTest extends TestCase
{
    public function testConvertsTextResult()
    {
        $result = (new LlamaResultConverter())->convert($this->createRawResult([
            'choices' => [['message' => ['content' => 'Visit the Louvre.'], 'finish_reason' => 'stop']],
        ]));

        $this->assertSame('Visit the Louvre.', $result->getContent());
    }

    public function testThrowsModelNotFoundExceptionForUnknownDeployment()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Not Found');

        (new LlamaResultConverter())->convert($this->createRawResult(['detail' => 'Not Found'], 404));
    }

    public function testThrowsAuthenticationExceptionForInvalidKey()
    {
        $this->expectException(AuthenticationException::class);

        (new LlamaResultConverter())->convert($this->createRawResult(['error' => ['message' => 'Invalid key']], 401));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createRawResult(array $body, int $status = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($body, ['http_code' => $status]));

        return new RawHttpResult($httpClient->request('POST', 'https://example.models.ai.azure.com/chat/completions'));
    }
}
