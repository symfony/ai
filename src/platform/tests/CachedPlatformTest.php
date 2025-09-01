<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\CachedPlatform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

final class CachedPlatformTest extends TestCase
{
    public function testPlatformCanReturnCachedResultWhenCalledTwice()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn(new TextResult('test content'));

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult($resultConverter, $rawHttpResult));

        $cachedPlatform = new CachedPlatform(
            $platform,
            new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
    }
}
