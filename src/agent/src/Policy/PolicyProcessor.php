<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Policy;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PolicyProcessor implements InputProcessorInterface, OutputProcessorInterface
{
    public function __construct(
        private readonly PolicyHandlerRegistryInterface $policyHandlerRegistry,
    ) {
    }

    public function processInput(Input $input): void
    {
        $policies = $input->getPolicies();

        if ([] === $policies) {
            return;
        }

        $inputPolicies = array_filter($policies, static fn (object $policy): bool => $policy instanceof InputPolicyInterface);

        foreach ($inputPolicies as $policy) {
            $handler = $this->policyHandlerRegistry->get($policy);

            $handler->handle($input->getMessageBag(), $input->getOptions(), $policy);
        }
    }

    public function processOutput(Output $output): void
    {
        $policies = $output->getPolicies();

        if ([] === $policies) {
            return;
        }

        $outputPolicies = array_filter($policies, static fn (object $policy): bool => $policy instanceof OutputPolicyInterface);

        foreach ($outputPolicies as $policy) {
            $handler = $this->policyHandlerRegistry->get($policy);

            $handler->handle($output->getMessageBag(), $output->getOptions(), $policy);
        }
    }
}
