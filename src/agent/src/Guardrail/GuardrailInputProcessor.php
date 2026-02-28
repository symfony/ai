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
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Input processor that runs registered input guardrails before sending messages to the LLM.
 *
 * If any guardrail tripwire is triggered, a {@see GuardrailException} is thrown
 * and the agent execution is halted.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class GuardrailInputProcessor implements InputProcessorInterface
{
    /**
     * @param iterable<InputGuardrailInterface> $guardrails
     */
    public function __construct(
        private readonly iterable $guardrails,
    ) {
    }

    /**
     * @throws GuardrailException when a guardrail tripwire is triggered
     */
    public function processInput(Input $input): void
    {
        foreach ($this->guardrails as $guardrail) {
            $result = $guardrail->validateInput($input);

            if ($result->isTriggered()) {
                throw new GuardrailException($result);
            }
        }
    }
}
