<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\HiggsfieldJobClient;
use Symfony\AI\Platform\Bridge\Higgsfield\HiggsfieldResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HiggsfieldResultConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = self::createConverter();

        $this->assertTrue($converter->supports(new Higgsfield('higgsfield-ai/soul/v2/standard')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertReturnsAJobHandleForTheSubmittedGeneration()
    {
        $result = self::createConverter()->convert(self::rawResult('{"request_id": "req-123", "status": "queued"}'));

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('req-123', $result->getContent()->getId());
        $this->assertSame('higgsfield', $result->getContent()->getProvider());
        $this->assertSame(HiggsfieldJobClient::DEFAULT_MAX_DURATION, $result->getContent()->getMaxDuration());
        $this->assertSame(HiggsfieldJobClient::DEFAULT_POLL_INTERVAL, $result->getContent()->getPollInterval());
        $this->assertSame('requests/req-123/status', $result->getContent()->get('status_path'));
    }

    public function testConvertNamesTheProviderTheJobClientServes()
    {
        $result = self::createConverter('my-higgsfield')->convert(self::rawResult('{"request_id": "req-123", "status": "queued"}'));

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('my-higgsfield', $result->getContent()->getProvider());
    }

    public function testConvertThrowsWhenRequestIdMissing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield API error: "invalid credentials".');

        self::createConverter()->convert(self::rawResult('{"detail": "invalid credentials"}'));
    }

    public function testConvertThrowsOnHttpError()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid credentials');

        self::createConverter()->convert(self::rawResult('{"detail": "invalid credentials"}', 401));
    }

    private static function createConverter(string $name = 'higgsfield'): HiggsfieldResultConverter
    {
        return new HiggsfieldResultConverter($name);
    }

    private static function rawResult(string $body, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://platform.higgsfield.ai/higgsfield-ai/soul/v2/standard'));
    }
}
