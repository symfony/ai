<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\TokenUsage;

use Symfony\AI\Agent\Output;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
interface TokenUsageExtractorInterface
{
    /**
     * Map provider raw response (or provider response + result) to TokenUsage or return null.
     */
    public function extractTokenUsage(Output $output): ?TokenUsage;
}
