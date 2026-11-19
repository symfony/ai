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
use Symfony\AI\Platform\Model;

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

        $model = $options['model'];
        if ('' === $model || (!\is_string($model) && !$model instanceof Model)) {
            throw new InvalidArgumentException(\sprintf('Option "model" must be a non-empty string or an instance of "%s".', Model::class));
        }

        $input->setModel($model);

        unset($options['model']);
        $input->setOptions($options);
    }
}
