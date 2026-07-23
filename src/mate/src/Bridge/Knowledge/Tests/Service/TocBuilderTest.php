<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TocBuilderTest extends TestCase
{
    private string $docsDir;

    protected function setUp(): void
    {
        $this->docsDir = \dirname(__DIR__).'/Fixtures/docs';
    }

    public function testBuildProducesNestedTreeFromToctrees()
    {
        $builder = new TocBuilder();

        $root = $builder->build($this->docsDir.'/index.rst', $this->docsDir);

        $this->assertSame('', $root->getPath());
        $this->assertSame('Sample Documentation', $root->getTitle());
        $this->assertCount(2, $root->getChildren());

        $children = $root->getChildren();
        $this->assertSame('setup', $children[0]->getPath());
        $this->assertSame('Setup Guide', $children[0]->getTitle());
        $this->assertSame([], $children[0]->getChildren());

        $this->assertSame('advanced', $children[1]->getPath());
        $this->assertSame('Advanced Topics', $children[1]->getTitle());
        $this->assertCount(1, $children[1]->getChildren());

        $caching = $children[1]->getChildren()[0];
        $this->assertSame('advanced/caching', $caching->getPath());
        $this->assertSame('Caching', $caching->getTitle());
    }

    public function testFindByPathLocatesNestedNodes()
    {
        $builder = new TocBuilder();
        $root = $builder->build($this->docsDir.'/index.rst', $this->docsDir);

        $node = $root->findByPath('advanced/caching');

        $this->assertNotNull($node);
        $this->assertSame('Caching', $node->getTitle());
    }
}
