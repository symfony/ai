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

use Symfony\AI\Agent\Output;

/**
 * Validates agent output after it is received from the LLM.
 *
 * Implementations scan LLM responses for potential threats such as
 * leaked system prompts, sensitive data, or restricted content.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
interface OutputGuardrailInterface
{
    public function validateOutput(Output $output): GuardrailResult;
}
