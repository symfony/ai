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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VoiceProviderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $voiceProviders = $container->findTaggedServiceIds('ai.voice.provider');
    }
}
