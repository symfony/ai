<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResponse extends BaseResponse
{
    public function __construct(
        private readonly \Generator $generator,
    ) {
    }

    public function getContent(): \Generator
    {
        yield from $this->generator;
    }
}
