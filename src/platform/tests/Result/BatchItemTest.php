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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BatchItem;
use Symfony\AI\Platform\Result\BatchItemCase;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchItemTest extends TestCase
{
    public function testASucceededItemCarriesTheResultOfItsRequest()
    {
        $result = new TextResult('Paris');
        $item = BatchItem::succeeded('capital-fr', $result);

        $this->assertSame('capital-fr', $item->getId());
        $this->assertTrue($item->isSuccess());
        $this->assertTrue($item->is(BatchItemCase::SUCCEEDED));
        $this->assertSame($result, $item->getResult());
        $this->assertNull($item->getError());
    }

    public function testAnErroredItemCarriesTheReasonThereIsNoResult()
    {
        $item = BatchItem::errored('capital-xx', 'Unknown model.');

        $this->assertSame('capital-xx', $item->getId());
        $this->assertFalse($item->isSuccess());
        $this->assertSame(BatchItemCase::ERRORED, $item->getCase());
        $this->assertSame('Unknown model.', $item->getError());
    }

    public function testACanceledRequestIsNotAnErroredOne()
    {
        $item = BatchItem::canceled('capital-xx');

        $this->assertFalse($item->isSuccess());
        $this->assertFalse($item->is(BatchItemCase::ERRORED));
        $this->assertSame(BatchItemCase::CANCELED, $item->getCase());
        $this->assertNotNull($item->getError());
    }

    public function testAnExpiredRequestIsNotAnErroredOne()
    {
        $item = BatchItem::expired('capital-xx');

        $this->assertSame(BatchItemCase::EXPIRED, $item->getCase());
        $this->assertFalse($item->is(BatchItemCase::ERRORED, BatchItemCase::SUCCEEDED));
    }

    public function testAnOutcomeThisEnumDoesNotKnowKeepsTheProviderWording()
    {
        $item = BatchItem::unknown('capital-xx', 'throttled');

        $this->assertSame(BatchItemCase::UNKNOWN, $item->getCase());
        $this->assertSame('throttled', $item->getRaw());
    }

    public function testItKeepsTheProviderWordingOfAKnownOutcome()
    {
        $this->assertSame('canceled', BatchItem::canceled('capital-xx', 'canceled')->getRaw());
        // A provider that states no wording of its own, e.g. because the outcome is read off an
        // HTTP status code, leaves it unset.
        $this->assertNull(BatchItem::canceled('capital-xx')->getRaw());
    }

    public function testAnItemWithoutAResultHasNoneToHandOut()
    {
        $item = BatchItem::errored('capital-xx', 'Unknown model.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The batch request "capital-xx" did not produce a result: "Unknown model."');

        $item->getResult();
    }
}
