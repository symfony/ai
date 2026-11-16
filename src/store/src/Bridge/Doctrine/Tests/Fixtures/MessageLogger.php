<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures;

use Psr\Log\AbstractLogger;

final class MessageLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (\is_scalar($value)) {
                $replacements['{'.$key.'}'] = (string) $value;
            }
        }

        $this->messages[] = strtr((string) $message, $replacements);
    }
}
