<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\Policy\InputDelayPolicy;
use Symfony\AI\AiBundle\Profiler\TraceableAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class TraceableAgentTest extends TestCase
{
    public function testDataAreCollected()
    {
        $traceableAgent = new TraceableAgent(new MockAgent([
            'Hello there' => 'General Kenobi',
        ]));

        $messages = new MessageBag(
            Message::ofUser('Hello there'),
        );

        $traceableAgent->call($messages, [
            'stream' => true,
        ], [
            new InputDelayPolicy(1),
        ]);

        $this->assertCount(1, $traceableAgent->calls);
        $this->assertEquals([
            'messages' => $messages,
            'options' => [
                'stream' => true,
            ],
            'policies' => [
                new InputDelayPolicy(1),
            ],
        ], $traceableAgent->calls[0]);
    }
}
