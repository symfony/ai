<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\OutputProcessor;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Result\ResultHandlerInterface;

readonly class ResultOutputProcessor implements OutputProcessorInterface
{
    public function __construct(
        private ResultHandlerInterface $resultProcessor,
    ) {
    }

    public function processOutput(Output $output): void
    {
        $this->resultProcessor->handleResult($output->result);
    }
}
