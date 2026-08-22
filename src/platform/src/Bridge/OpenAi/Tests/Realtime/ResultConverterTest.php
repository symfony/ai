<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Realtime;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsRealtimeModel()
    {
        $converter = new ResultConverter();
        $model = new Realtime('gpt-4o-realtime-preview');

        $this->assertTrue($converter->supports($model));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();
        $model = new Model('other-model');

        $this->assertFalse($converter->supports($model));
    }

    public function testThrowsOnAuthenticationError()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('toArray')->willReturn(['error' => ['message' => 'Unauthorized']]);

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testThrowsOnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI Realtime API returned an error: "Unexpected"');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(418);
        $response->method('getContent')->willReturn('Unexpected');

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertsRealtimeSessionResponse()
    {
        $mockData = [
            'id' => 'sess_test_999',
            'object' => 'realtime.session',
            'model' => 'gpt-4o-realtime-preview',
            'modalities' => ['text', 'audio'],
            'instructions' => 'Speak clearly',
            'voice' => 'alloy',
            'client_secret' => [
                'value' => 'ek_secret_token_abc',
                'expires_at' => 1795000000,
            ],
        ];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn($mockData);

        $rawResult = new RawHttpResult($response);

        $converter = new ResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame('sess_test_999', $result->getId());
        $this->assertSame('ek_secret_token_abc', $result->getClientSecret());
        $this->assertSame(1795000000, $result->getExpiresAt());
        $this->assertSame('gpt-4o-realtime-preview', $result->getModel());
        $this->assertSame('alloy', $result->getVoice());
        $this->assertSame(['text', 'audio'], $result->getModalities());
        $this->assertSame([
            'id' => 'sess_test_999',
            'client_secret' => 'ek_secret_token_abc',
            'expires_at' => 1795000000,
            'model' => 'gpt-4o-realtime-preview',
            'voice' => 'alloy',
            'modalities' => ['text', 'audio'],
        ], $result->getContent());
    }
}
