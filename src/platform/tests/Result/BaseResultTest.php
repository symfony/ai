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

use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;
use Symfony\AI\Platform\Result\RawResultAwareTrait;
use Symfony\AI\Platform\Result\RawResultInterface;

#[UsesTrait(MetadataAwareTrait::class)]
#[UsesTrait(RawResultAwareTrait::class)]
final class BaseResultTest extends TestCase
{
    public function testItCanHandleMetadata()
    {
        $result = $this->createResult();
        $metadata = $result->getMetadata();

        $this->assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $result->getMetadata();

        $this->assertCount(1, $metadata);
    }

    public function testItCanBeEnrichedWithARawResponse()
    {
        $result = $this->createResult();
        $rawResult = $this->createRawResult();

        $result->setRawResult($rawResult);
        $this->assertSame($rawResult, $result->getRawResult());
    }

    public function testItThrowsAnExceptionWhenSettingARawResponseTwice()
    {
        $this->expectException(RawResultAlreadySetException::class);

        $result = $this->createResult();
        $rawResult = $this->createRawResult();

        $result->setRawResult($rawResult);
        $result->setRawResult($rawResult);
    }

    public function createRawResult(): RawResultInterface
    {
        return new class implements RawResultInterface {
            public function getData(): array
            {
                return ['key' => 'value'];
            }

            public function getDataStream(): iterable
            {
                return $this->getData();
            }

            public function getObject(): object
            {
                return new \stdClass();
            }
        };
    }

    private function createResult(): BaseResult
    {
        return new class extends BaseResult {
            public function getContent(): string
            {
                return 'test';
            }
        };
    }
}
