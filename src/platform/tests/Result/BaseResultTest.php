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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\RawResultAwareTrait;
use Symfony\AI\Platform\Result\RawResultInterface;

#[CoversClass(BaseResult::class)]
#[UsesTrait(MetadataAwareTrait::class)]
#[UsesTrait(RawResultAwareTrait::class)]
#[UsesClass(Metadata::class)]
#[UsesClass(RawResultAlreadySetException::class)]
#[Small]
final class BaseResultTest extends TestCase
{
    #[Test]
    public function itCanHandleMetadata(): void
    {
        $result = $this->createResponse();
        $metadata = $result->getMetadata();

        self::assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $result->getMetadata();

        self::assertCount(1, $metadata);
    }

    #[Test]
    public function itCanBeEnrichedWithARawResponse(): void
    {
        $result = $this->createResponse();
        $rawResponse = $this->createRawResponse();

        $result->setRawResult($rawResponse);
        self::assertSame($rawResponse, $result->getRawResult());
    }

    #[Test]
    public function itThrowsAnExceptionWhenSettingARawResponseTwice(): void
    {
        self::expectException(RawResultAlreadySetException::class);

        $result = $this->createResponse();
        $rawResponse = $this->createRawResponse();

        $result->setRawResult($rawResponse);
        $result->setRawResult($rawResponse);
    }

    private function createResponse(): BaseResult
    {
        return new class extends BaseResult {
            public function getContent(): string
            {
                return 'test';
            }
        };
    }

    public function createRawResponse(): RawResultInterface
    {
        return new class implements RawResultInterface {
            public function getData(): array
            {
                return ['key' => 'value'];
            }

            public function getObject(): object
            {
                return new \stdClass();
            }
        };
    }
}
