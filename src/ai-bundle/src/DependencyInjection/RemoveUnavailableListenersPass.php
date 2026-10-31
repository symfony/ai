<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the tool call listeners when the service they depend on is not defined,
 * e.g. symfony/security-core is installed without the SecurityBundle.
 *
 * Runs before the attribute autoconfiguration, so that #[IsGrantedTool] fails without an authorization checker.
 */
final class RemoveUnavailableListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('ai.security.is_granted_attribute_listener') && !$container->has('security.authorization_checker')) {
            $container->removeDefinition('ai.security.is_granted_attribute_listener');
            $container->registerAttributeForAutoconfiguration(
                IsGrantedTool::class,
                static fn () => throw new InvalidArgumentException('Using #[IsGrantedTool] attribute requires additional dependencies. Try running "composer install symfony/security-core".'),
            );
        }

        if ($container->hasDefinition('ai.tool.validate_tool_call_arguments_listener') && !$container->has('validator')) {
            $container->removeDefinition('ai.tool.validate_tool_call_arguments_listener');
        }
    }
}
