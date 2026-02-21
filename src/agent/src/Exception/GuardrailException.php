<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

use Symfony\AI\Agent\Guardrail\GuardrailResult;

/**
 * Exception thrown when a guardrail tripwire is triggered.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class GuardrailException extends RuntimeException
{
    public function __construct(
        private readonly GuardrailResult $guardrailResult,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Guardrail "%s" triggered: %s (score: %.2f)', $guardrailResult->getScanner(), $guardrailResult->getReason() ?? 'unknown reason', $guardrailResult->getScore()), previous: $previous);
    }

    public function getGuardrailResult(): GuardrailResult
    {
        return $this->guardrailResult;
    }
}
