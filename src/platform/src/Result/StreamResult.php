<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Metadata\Metadata;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResult extends BaseResult
{
    public function __construct(
        private readonly \Generator $generator,
    ) {
    }

    public function getContent(): \Generator
    {
        foreach ($this->generator as $content) {
            if ($content instanceof Metadata) {
                foreach ($content as $key => $value) {
                    $this->getMetadata()->add($key, $value);
                }
                continue;
            }

            yield $content;
        }
    }
}
