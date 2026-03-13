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
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\SleepTime\MemoryBlock;
use Symfony\AI\Agent\SleepTime\MemoryBlockProvider;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

final class MemoryBlockProviderTest extends TestCase
{
    public function testLoadReturnsEmptyArrayWhenAllBlocksAreEmpty()
    {
        $provider = new MemoryBlockProvider([
            new MemoryBlock('summary'),
            new MemoryBlock('preferences'),
        ]);

        $input = new Input('gpt-4o', new MessageBag(new UserMessage(new Text('Hello'))));

        $this->assertSame([], $provider->load($input));
    }

    public function testLoadReturnsMemoryForNonEmptyBlocks()
    {
        $provider = new MemoryBlockProvider([
            new MemoryBlock('summary', 'User discussed PHP patterns'),
        ]);

        $input = new Input('gpt-4o', new MessageBag(new UserMessage(new Text('Hello'))));

        $memories = $provider->load($input);

        $this->assertCount(1, $memories);
        $this->assertStringContainsString('summary', $memories[0]->getContent());
        $this->assertStringContainsString('User discussed PHP patterns', $memories[0]->getContent());
    }

    public function testLoadSkipsEmptyBlocks()
    {
        $provider = new MemoryBlockProvider([
            new MemoryBlock('summary', 'Some summary'),
            new MemoryBlock('preferences'),
            new MemoryBlock('context', 'Some context'),
        ]);

        $input = new Input('gpt-4o', new MessageBag(new UserMessage(new Text('Hello'))));

        $memories = $provider->load($input);

        $this->assertCount(1, $memories);
        $this->assertStringContainsString('summary', $memories[0]->getContent());
        $this->assertStringContainsString('Some summary', $memories[0]->getContent());
        $this->assertStringContainsString('context', $memories[0]->getContent());
        $this->assertStringContainsString('Some context', $memories[0]->getContent());
        $this->assertStringNotContainsString('preferences', $memories[0]->getContent());
    }

    public function testLoadFormatsContentWithSleepTimeHeader()
    {
        $provider = new MemoryBlockProvider([
            new MemoryBlock('summary', 'Content here'),
        ]);

        $input = new Input('gpt-4o', new MessageBag(new UserMessage(new Text('Hello'))));

        $memories = $provider->load($input);

        $this->assertStringStartsWith('## Sleep-Time Memory', $memories[0]->getContent());
    }
}
