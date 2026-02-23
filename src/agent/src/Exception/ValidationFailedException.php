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

use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ValidationFailedException extends RuntimeException
{
    public function __construct(
        private readonly MessageInterface $violatingMessage,
        private readonly ConstraintViolationListInterface $violations,
    ) {
        parent::__construct();
    }

    public function getViolatingMessage(): MessageInterface
    {
        return $this->violatingMessage;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
