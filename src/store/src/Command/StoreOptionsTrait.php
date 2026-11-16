<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Command;

use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
trait StoreOptionsTrait
{
    private function addStoreOptionsOption(): void
    {
        $this->addOption('option', 'o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Option passed to the store as "name=value", the value is decoded as JSON if possible, a bare "name" is set to true');
    }

    /**
     * @return array<string, mixed>
     */
    private function getStoreOptions(InputInterface $input): array
    {
        $options = [];
        foreach ($input->getOption('option') as $option) {
            [$name, $value] = explode('=', $option, 2) + [1 => null];

            if ('' === $name) {
                throw new InvalidArgumentException(\sprintf('The option "%s" must be given as "name=value".', $option));
            }

            if (null === $value) {
                $options[$name] = true;

                continue;
            }

            try {
                $options[$name] = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $options[$name] = $value;
            }
        }

        return $options;
    }
}
