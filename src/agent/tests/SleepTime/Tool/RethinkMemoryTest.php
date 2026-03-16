<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\SleepTime\Tool;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\SleepTime\MemoryBlock;
use Symfony\AI\Agent\SleepTime\Tool\RethinkMemory;

final class RethinkMemoryTest extends TestCase
{
    public function testInvokeUpdatesExistingBlock()
    {
        $block = new MemoryBlock('summary');
        $tool = new RethinkMemory([$block]);

        $result = ($tool)('summary', 'New insights about the conversation');

        $this->assertSame('New insights about the conversation', $block->getContent());
        $this->assertStringContainsString('summary', $result);
        $this->assertStringContainsString('updated successfully', $result);
    }

    public function testInvokeThrowsForNonExistentLabel()
    {
        $tool = new RethinkMemory([
            new MemoryBlock('summary'),
            new MemoryBlock('preferences'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory block "unknown" not found. Available labels: "summary", "preferences".');

        ($tool)('unknown', 'Some content');
    }

    public function testInvokeOverwritesPreviousBlockContent()
    {
        $block = new MemoryBlock('summary', 'Old content');
        $tool = new RethinkMemory([$block]);

        ($tool)('summary', 'Updated content');

        $this->assertSame('Updated content', $block->getContent());
    }

    public function testInvokeOnlyUpdatesTargetedBlock()
    {
        $summary = new MemoryBlock('summary', 'Summary content');
        $preferences = new MemoryBlock('preferences', 'Preferences content');
        $tool = new RethinkMemory([$summary, $preferences]);

        ($tool)('summary', 'New summary');

        $this->assertSame('New summary', $summary->getContent());
        $this->assertSame('Preferences content', $preferences->getContent());
    }
}
