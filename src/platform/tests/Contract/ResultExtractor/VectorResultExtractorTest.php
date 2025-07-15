<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\ResultExtractor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\ResultExtractor\VectorResultExtractor;
use Symfony\Component\JsonPath\JsonCrawler;

#[CoversClass(VectorResultExtractor::class)]
final class VectorResultExtractorTest extends TestCase
{
    public function testStandardSuccess()
    {
        $json = file_get_contents(\dirname(__DIR__).'/fixtures/embeddings-default.json');

        $extractor = new VectorResultExtractor();

        $actual = $extractor->extract(new JsonCrawler($json));
        $this->assertCount(1, $actual);
        $this->assertCount(1, $actual[0]->getContent());
        $this->assertSame(5, $actual[0]->getContent()[0]->getDimensions());
    }

    public function testSpecificSuccess()
    {
        $json = file_get_contents(\dirname(__DIR__).'/fixtures/embeddings-gemini.json');

        $extractor = new VectorResultExtractor('$.embeddings[*].values');

        $actual = $extractor->extract(new JsonCrawler($json));
        $this->assertCount(1, $actual);
        $this->assertCount(1, $actual[0]->getContent());
        $this->assertSame(6, $actual[0]->getContent()[0]->getDimensions());
    }
}
