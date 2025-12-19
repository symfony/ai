<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\TokenUsage;

use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Implementations handle the extraction of token usage data from raw results.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TokenUsageExtractorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface;
}
