<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Guardrail;

use Symfony\AI\Agent\Exception\GuardrailException;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;

/**
 * Output processor that runs registered output guardrails after receiving LLM responses.
 *
 * If any guardrail tripwire is triggered, a {@see GuardrailException} is thrown
 * and the agent execution is halted.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class GuardrailOutputProcessor implements OutputProcessorInterface
{
    /**
     * @param iterable<OutputGuardrailInterface> $guardrails
     */
    public function __construct(
        private readonly iterable $guardrails,
    ) {
    }

    /**
     * @throws GuardrailException when a guardrail tripwire is triggered
     */
    public function processOutput(Output $output): void
    {
        foreach ($this->guardrails as $guardrail) {
            $result = $guardrail->validateOutput($output);

            if ($result->isTriggered()) {
                throw new GuardrailException($result);
            }
        }
    }
}
