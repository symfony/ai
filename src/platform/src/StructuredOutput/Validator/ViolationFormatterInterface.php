<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput\Validator;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Turns the violations of a rejected structured output into the message that asks the model to correct it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ViolationFormatterInterface
{
    public function format(ConstraintViolationListInterface $violations): string;
}
