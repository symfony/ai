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
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\DependencyInjection\RemoveUnavailableListenersPass;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\AI\AiBundle\Security\EventListener\IsGrantedToolAttributeListener;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnClass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RemoveUnavailableListenersPassTest extends TestCase
{
    public function testContainerCompilesWithoutSecurityBundleAndValidator()
    {
        $container = $this->loadContainer();
        $container->compile();

        $this->assertFalse($container->has('ai.security.is_granted_attribute_listener'));
        $this->assertFalse($container->has('ai.tool.validate_tool_call_arguments_listener'));
    }

    public function testIsGrantedToolFailsWithoutAuthorizationChecker()
    {
        $container = $this->loadContainer();
        $container->register(ToolWithIsGrantedOnClass::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Using #[IsGrantedTool] attribute requires additional dependencies.');

        $container->compile();
    }

    public function testKeepsTheListenersWithTheServicesTheyDependOn()
    {
        $container = $this->container();
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setDefinition('validator', new Definition());

        (new RemoveUnavailableListenersPass())->process($container);

        $this->assertTrue($container->hasDefinition('ai.security.is_granted_attribute_listener'));
        $this->assertTrue($container->hasDefinition('ai.tool.validate_tool_call_arguments_listener'));
        $this->assertArrayNotHasKey(IsGrantedTool::class, $container->getAttributeAutoconfigurators());
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setDefinition('event_dispatcher', (new Definition(EventDispatcher::class))->setPublic(true));
        $container->addCompilerPass(new RegisterListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);

        (new AiBundle())->getContainerExtension()->load([], $container);
        (new AiBundle())->build($container);

        return $container;
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('ai.security.is_granted_attribute_listener', new Definition(IsGrantedToolAttributeListener::class, [
            new Reference('security.authorization_checker'),
        ]));
        $container->setDefinition('ai.tool.validate_tool_call_arguments_listener', new Definition(ValidateToolCallArgumentsListener::class, [
            new Reference('validator'),
        ]));

        return $container;
    }
}
