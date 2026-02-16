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

use Symfony\AI\Agent\Input;

/**
 * Validates agent input before it is sent to the LLM.
 *
 * Implementations scan user messages for potential threats such as
 * prompt injections, invisible characters, or restricted content.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
interface InputGuardrailInterface
{
    public function validateInput(Input $input): GuardrailResult;
}
