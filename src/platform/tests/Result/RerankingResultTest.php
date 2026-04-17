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
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RerankingResult;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankingResultTest extends TestCase
{
    public function testGetContentWithSingleEntry()
    {
        $entry = new RerankingEntry(0, 0.95);
        $result = new RerankingResult([$entry]);

        $this->assertSame([$entry], $result->getContent());
    }

    public function testGetContentWithMultipleEntries()
    {
        $entry1 = new RerankingEntry(0, 0.95);
        $entry2 = new RerankingEntry(1, 0.80);
        $entry3 = new RerankingEntry(2, 0.60);

        $result = new RerankingResult([$entry1, $entry2, $entry3]);

        $this->assertSame([$entry1, $entry2, $entry3], $result->getContent());
    }

    public function testGetContentWithNoEntries()
    {
        $result = new RerankingResult();

        $this->assertSame([], $result->getContent());
    }

    public function testConstructorAcceptsArrayOfEntries()
    {
        $entries = [
            new RerankingEntry(0, 0.9),
            new RerankingEntry(1, 0.7),
            new RerankingEntry(2, 0.5),
        ];

        $result = new RerankingResult($entries);

        $this->assertSame($entries, $result->getContent());
    }
}
