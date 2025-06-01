<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ToolResultConverter
{
    /**
     * @param \JsonSerializable|\Stringable|array<int|string, mixed>|float|string|null $result
     */
    public function convert(\JsonSerializable|\Stringable|array|float|string|\DateTimeInterface|null $result): ?string
    {
        if (null === $result) {
            return null;
        }

        if ($result instanceof \JsonSerializable || \is_array($result)) {
            return json_encode($result, flags: \JSON_THROW_ON_ERROR);
        }

        if (\is_float($result) || $result instanceof \Stringable) {
            return (string) $result;
        }

        if ($result instanceof \DateTimeInterface) {
            return $result->format(\DATE_ATOM);
        }

        return $result;
    }
}
