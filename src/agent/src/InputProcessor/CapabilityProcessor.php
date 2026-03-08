<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\InputProcessor;

use Symfony\AI\Agent\AgentAwareTrait;
use Symfony\AI\Agent\Capability\CapabilityHandlerRegistryInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CapabilityProcessor implements InputProcessorInterface, OutputProcessorInterface
{
    use AgentAwareTrait;

    public function __construct(
        private readonly CapabilityHandlerRegistryInterface $policyHandlerRegistry,
    ) {
    }

    public function processInput(Input $input): void
    {
        $inputCapabilities = $input->getCapabilities();

        if ([] === $inputCapabilities) {
            return;
        }

        foreach ($inputCapabilities as $capability) {
            $handler = $this->policyHandlerRegistry->get($capability);

            $handler->handle($this->agent, $input->getMessageBag(), $input->getOptions(), $capability);
        }
    }

    public function processOutput(Output $output): void
    {
        $outputCapabilities = $output->getCapabilities();

        if ([] === $outputCapabilities) {
            return;
        }

        foreach ($outputCapabilities as $capability) {
            $handler = $this->policyHandlerRegistry->get($capability);

            $handler->handle($this->agent, $output->getMessageBag(), $output->getOptions(), $capability);
        }
    }
}
