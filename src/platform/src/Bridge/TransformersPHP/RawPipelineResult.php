<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPHP;

use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class RawPipelineResult implements RawResultInterface
{
    public function __construct(
        private PipelineExecution $pipelineExecution,
    ) {
    }

    public function getData(): array
    {
        return $this->pipelineExecution->getResult();
    }

    public function getObject(): PipelineExecution
    {
        return $this->pipelineExecution;
    }
}
