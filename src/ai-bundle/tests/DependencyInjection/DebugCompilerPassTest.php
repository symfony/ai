<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\DependencyInjection\DebugCompilerPass;
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DebugCompilerPassTest extends TestCase
{
    public function testProcessAddsTraceableDefinitionsInDebug()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $container->register('ai.platform.azure.eu', \stdClass::class)->addTag('ai.platform');
        $container->register('ai.message_store.memory.main', \stdClass::class)->addTag('ai.message_store');
        $container->register('ai.chat.main', \stdClass::class)->addTag('ai.chat');
        $container->register('ai.toolbox.my_agent', \stdClass::class)->addTag('ai.toolbox');

        (new DebugCompilerPass())->process($container);

        $traceablePlatform = $container->getDefinition('ai.traceable_platform.azure.eu');
        $this->assertSame(TraceablePlatform::class, $traceablePlatform->getClass());
        $this->assertSame(['ai.platform.azure.eu', null, -1024], $traceablePlatform->getDecoratedService());
        $this->assertEquals([new Reference('.inner')], $traceablePlatform->getArguments());
        $this->assertTrue($traceablePlatform->hasTag('ai.traceable_platform'));
        $this->assertSame([['method' => 'reset']], $traceablePlatform->getTag('kernel.reset'));

        $traceableMessageStore = $container->getDefinition('ai.traceable_message_store.main');
        $this->assertSame(TraceableMessageStore::class, $traceableMessageStore->getClass());
        $this->assertSame(['ai.message_store.memory.main', null, -1024], $traceableMessageStore->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference(ClockInterface::class)], $traceableMessageStore->getArguments());
        $this->assertTrue($traceableMessageStore->hasTag('ai.traceable_message_store'));
        $this->assertSame([['method' => 'reset']], $traceableMessageStore->getTag('kernel.reset'));

        $traceableChat = $container->getDefinition('ai.traceable_chat.main');
        $this->assertSame(TraceableChat::class, $traceableChat->getClass());
        $this->assertSame(['ai.chat.main', null, -1024], $traceableChat->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference(ClockInterface::class)], $traceableChat->getArguments());
        $this->assertTrue($traceableChat->hasTag('ai.traceable_chat'));
        $this->assertSame([['method' => 'reset']], $traceableChat->getTag('kernel.reset'));

        $traceableToolbox = $container->getDefinition('ai.traceable_toolbox.my_agent');
        $this->assertSame(TraceableToolbox::class, $traceableToolbox->getClass());
        $this->assertSame(['ai.toolbox.my_agent', null, -1024], $traceableToolbox->getDecoratedService());
        $this->assertEquals([new Reference('.inner')], $traceableToolbox->getArguments());
        $this->assertTrue($traceableToolbox->hasTag('ai.traceable_toolbox'));
        $this->assertSame([['method' => 'reset']], $traceableToolbox->getTag('kernel.reset'));
    }

    public function testProcessSkipsWhenDebugDisabled()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('ai.platform.anthropic', \stdClass::class)->addTag('ai.platform');
        $container->register('ai.message_store.memory.main', \stdClass::class)->addTag('ai.message_store');
        $container->register('ai.chat.main', \stdClass::class)->addTag('ai.chat');
        $container->register('ai.toolbox.my_agent', \stdClass::class)->addTag('ai.toolbox');

        (new DebugCompilerPass())->process($container);

        $this->assertFalse($container->hasDefinition('ai.traceable_platform.anthropic'));
        $this->assertFalse($container->hasDefinition('ai.traceable_message_store.main'));
        $this->assertFalse($container->hasDefinition('ai.traceable_chat.main'));
        $this->assertFalse($container->hasDefinition('ai.traceable_toolbox.my_agent'));
    }
}
