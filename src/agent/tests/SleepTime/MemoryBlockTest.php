<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\SleepTime;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\SleepTime\MemoryBlock;

final class MemoryBlockTest extends TestCase
{
    public function testConstructorSetsLabelAndEmptyContentByDefault()
    {
        $block = new MemoryBlock('summary');

        $this->assertSame('summary', $block->getLabel());
        $this->assertSame('', $block->getContent());
    }

    public function testConstructorSetsLabelAndContent()
    {
        $block = new MemoryBlock('summary', 'Initial content');

        $this->assertSame('summary', $block->getLabel());
        $this->assertSame('Initial content', $block->getContent());
    }

    public function testSetContentUpdatesContent()
    {
        $block = new MemoryBlock('summary');

        $block->setContent('New content');

        $this->assertSame('New content', $block->getContent());
    }

    public function testSetContentOverwritesPreviousContent()
    {
        $block = new MemoryBlock('summary', 'Old content');

        $block->setContent('New content');

        $this->assertSame('New content', $block->getContent());
    }
}
