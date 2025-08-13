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
use Symfony\AI\Agent\OutputProcessorInterface;

final readonly class TokenUsageOutputProcessor implements OutputProcessorInterface
{
    public function __construct(private TokenUsageExtractorInterface $extractor)
    {
    }

    public function processOutput(Output $output): void
    {
        if (null === $tokenUsage = $this->extractor->extractTokenUsage($output)) {
            return;
        }

        $output->result->setTokenUsage($tokenUsage);
    }
}
