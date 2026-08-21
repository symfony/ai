<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Discovery\DocBlockParser;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DocBlockParserTest extends TestCase
{
    private DocBlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DocBlockParser();
    }

    public function testGetSummaryReturnsLeadingBlock()
    {
        $doc = <<<'DOC'
            /**
             * Does a thing.
             *
             * @param int $x A number
             */
            DOC;

        $this->assertSame('Does a thing.', $this->parser->getSummary($doc));
    }

    public function testGetSummaryReturnsNullWhenOnlyTags()
    {
        $doc = "/**\n * @param int \$x A number\n */";

        $this->assertNull($this->parser->getSummary($doc));
    }

    public function testGetSummaryHandlesMissingDocComment()
    {
        $this->assertNull($this->parser->getSummary(false));
        $this->assertNull($this->parser->getSummary(null));
    }

    public function testGetParamTagsParsesTypeAndDescription()
    {
        $doc = <<<'DOC'
            /**
             * @param int         $limit  Maximum number of items
             * @param string|null $filter Optional filter
             * @param bool         $deep
             */
            DOC;

        $tags = $this->parser->getParamTags($doc);

        $this->assertSame(['type' => 'int', 'description' => 'Maximum number of items'], $tags['limit']);
        $this->assertSame(['type' => 'string|null', 'description' => 'Optional filter'], $tags['filter']);
        $this->assertSame(['type' => 'bool', 'description' => null], $tags['deep']);
    }

    public function testGetParamTagsWithoutType()
    {
        $doc = "/**\n * @param \$value Some value\n */";

        $tags = $this->parser->getParamTags($doc);

        $this->assertSame(['type' => null, 'description' => 'Some value'], $tags['value']);
    }
}
