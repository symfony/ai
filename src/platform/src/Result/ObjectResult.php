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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ObjectResult extends BaseResult
{
    /**
     * @param object|array<string, mixed> $structuredOutput
     * @param string|null                 $text             the JSON the model answered with
     */
    public function __construct(
        private readonly object|array $structuredOutput,
        private readonly ?string $text = null,
    ) {
    }

    /**
     * @return object|array<string, mixed>
     */
    public function getContent(): object|array
    {
        return $this->structuredOutput;
    }

    public function getText(): ?string
    {
        return $this->text;
    }
}
