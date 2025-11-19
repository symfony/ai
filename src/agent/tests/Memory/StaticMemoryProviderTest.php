<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Memory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Platform\Message\MessageBag;

final class StaticMemoryProviderTest extends TestCase
{
    public function testItsReturnsNullWhenNoFactsAreProvided()
    {
        $provider = new StaticMemoryProvider();

        $memory = $provider->load(new Input('gpt-4', new MessageBag(), []));

        $this->assertCount(0, $memory);
    }

    public function testItDeliversFormattedFacts()
    {
        $provider = new StaticMemoryProvider(
            $fact1 = 'The sky is blue',
            $fact2 = 'Water is wet',
        );

        $memory = $provider->load(new Input('gpt-4', new MessageBag(), []));

        $this->assertCount(1, $memory);
        $this->assertInstanceOf(Memory::class, $memory[0]);
        $expectedContent = "## Static Memory\n\n- {$fact1}\n- {$fact2}";
        $this->assertSame($expectedContent, $memory[0]->getContent());
    }
}
