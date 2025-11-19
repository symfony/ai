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

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultAwareTrait;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

#[CoversTrait(RawResultAwareTrait::class)]
final class RawResultAwareTraitTest extends TestCase
{
    public function testItCanBeEnrichedWithARawResponse()
    {
        $result = $this->createTestClass();
        $rawResponse = self::createMock(SymfonyHttpResponse::class);

        $result->setRawResult(new RawHttpResult($rawResponse));
        $this->assertSame($rawResponse, $result->getRawResult()?->getObject());
    }

    public function testItThrowsAnExceptionWhenSettingARawResponseTwice()
    {
        $this->expectException(RawResultAlreadySetException::class);

        $result = $this->createTestClass();
        $rawResponse = self::createMock(SymfonyHttpResponse::class);

        $result->setRawResult(new RawHttpResult($rawResponse));
        $result->setRawResult(new RawHttpResult($rawResponse));
    }

    private function createTestClass(): object
    {
        return new class {
            use RawResultAwareTrait;
        };
    }
}
