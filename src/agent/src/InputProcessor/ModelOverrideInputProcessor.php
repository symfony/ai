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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelOverrideInputProcessor implements InputProcessorInterface
{
    public function processInput(Input $input): void
    {
        $options = $input->getOptions();

        if (!\array_key_exists('model', $options)) {
            return;
        }

        if (!\is_string($options['model'])) {
            throw new InvalidArgumentException('Option "model" must be a string.');
        }

        $input->setModel($options['model']);
    }
}
