<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Output;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Yes/no probability on a scale from 0 (no) to 1 (yes).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class NoulAnswer
{
    public function __construct(
        private readonly float $noul,
    ) {
    }

    public function getNoul(): float
    {
        return $this->noul;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['noul']) || !is_numeric($data['noul'])) {
            throw new RuntimeException('Noul answer is missing a numeric "noul" value.');
        }

        return new self((float) $data['noul']);
    }

    /**
     * @return array{type: 'noul', noul: float}
     */
    public function toArray(): array
    {
        return [
            'type' => 'noul',
            'noul' => $this->noul,
        ];
    }
}
