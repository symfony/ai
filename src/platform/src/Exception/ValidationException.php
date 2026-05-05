<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidationException extends RuntimeException
{
    public function __construct(
        private readonly ConstraintViolationListInterface $violations,
    ) {
        parent::__construct('Validation failed for the structured output.');
    }

    public function getViolations(): object
    {
        return $this->violations;
    }
}
