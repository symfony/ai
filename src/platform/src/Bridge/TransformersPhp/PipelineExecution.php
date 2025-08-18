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

use Codewithkyrian\Transformers\Pipelines\Pipeline;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PipelineExecution
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $result = null;

    /**
     * @param array<mixed>|string $input
     */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly array $input,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getResult(): array
    {
        if (null === $this->result) {
            $this->result = ($this->pipeline)($this->input);
        }

        return $this->result;
    }
}
