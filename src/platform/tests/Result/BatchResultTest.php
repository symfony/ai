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
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\BatchItem;
use Symfony\AI\Platform\Result\BatchResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchResultTest extends TestCase
{
    public function testItHoldsTheItemsItWasGiven()
    {
        $items = [BatchItem::succeeded('first', new TextResult('Paris'))];

        $this->assertSame($items, (new BatchResult($items))->getContent());
    }

    public function testItLetsTheBridgeProduceItemsWhileTheyAreRead()
    {
        $read = 0;
        $items = (static function () use (&$read): \Generator {
            foreach (['Paris', 'Berlin', 'Rome'] as $index => $city) {
                ++$read;

                yield BatchItem::succeeded('city-'.$index, new TextResult($city));
            }
        })();

        $result = new BatchResult($items);

        foreach ($result->getContent() as $item) {
            $this->assertSame('Paris', $item->getResult()->getContent());
            break;
        }

        // The remaining items were never downloaded, let alone converted.
        $this->assertSame(1, $read);
    }

    public function testItIsReachedThroughTheSameAccessorsAsAnyOtherResult()
    {
        $deferredResult = new DeferredResult(
            new PlainConverter(new BatchResult([
                BatchItem::succeeded('capital-fr', new TextResult('Paris')),
                BatchItem::errored('capital-xx', 'Unknown model.'),
            ])),
            new InMemoryRawResult(),
        );

        $items = iterator_to_array($deferredResult->asBatch(), false);

        $this->assertCount(2, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
        $this->assertSame('Unknown model.', $items[1]->getError());
    }
}
