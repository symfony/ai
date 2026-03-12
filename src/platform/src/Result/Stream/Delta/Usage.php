<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Usage implements DeltaInterface
{
    /**
     * @param array<string, mixed> $usage
     */
    public function __construct(
        private readonly array $usage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(): array
    {
        return $this->usage;
    }
}
