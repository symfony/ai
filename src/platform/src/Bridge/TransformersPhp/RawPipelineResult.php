<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawPipelineResult implements RawResultInterface
{
    public function __construct(
        private readonly PipelineExecution $pipelineExecution,
    ) {
    }

    public function getData(): array
    {
        return $this->pipelineExecution->getResult();
    }

    public function getDataStream(): iterable
    {
        throw new RuntimeException('Streaming is not implemented yet.');
    }

    public function getObject(): PipelineExecution
    {
        return $this->pipelineExecution;
    }
}
