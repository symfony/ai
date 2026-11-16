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
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\Exception\RuntimeException;
use Symfony\AI\AiBundle\Security\EventListener\IsGrantedToolAttributeListener;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnMethod;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Lazizbek Ergashev <lazerg2@gmail.com>
 */
final class ToolCallListenersTest extends TestCase
{
    public function testListenersAreKeptWithoutSecurityBundleAndValidator()
    {
        $container = $this->buildContainer();
        $container->compile();

        $listeners = array_map(static fn (array $listener): string => $listener[0]::class, $container->get('event_dispatcher')->getListeners(ToolCallArgumentsResolved::class));
        sort($listeners);

        $this->assertSame([ValidateToolCallArgumentsListener::class, IsGrantedToolAttributeListener::class], $listeners);
    }

    public function testIsGrantedToolFailsWithoutSecurityBundleForToolsRegisteredWithoutAutoconfiguration()
    {
        $container = $this->buildContainer();
        $container->register(ToolWithIsGrantedOnMethod::class)
            ->setAutoconfigured(false)
            ->setPublic(true)
            ->addTag('ai.tool', ['name' => 'simple', 'description' => 'simple', 'method' => 'simple']);
        $container->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Using #[IsGrantedTool] attribute requires the SecurityBundle.');

        $container->get('event_dispatcher')->dispatch(new ToolCallArgumentsResolved(
            $container->get(ToolWithIsGrantedOnMethod::class),
            new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'simple'), 'simple', 'simple'),
            [],
        ));
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setDefinition('event_dispatcher', (new Definition(EventDispatcher::class))->setPublic(true));
        $container->addCompilerPass(new RegisterListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $bundle = new AiBundle();
        $bundle->getContainerExtension()->load([], $container);
        $bundle->build($container);

        return $container;
    }
}
