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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OutputResponse extends BaseResponse
{
    /**
     * @var Output[]
     */
    private readonly array $outputs;

    public function __construct(Output ...$outputs)
    {
        if ([] === $outputs) {
            throw new InvalidArgumentException('Response must have at least one output.');
        }

        $this->outputs = $outputs;
    }

    /**
     * @return Output[]
     */
    public function getContent(): array
    {
        return $this->outputs;
    }
}
